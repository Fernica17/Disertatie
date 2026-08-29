"""Storage for face embeddings.

Lives in its own PostgreSQL schema (`face`) inside the application database, so
the ERP and this service share a backup and a connection endpoint without the
Python service ever touching Doctrine-managed tables.

Embeddings are stored as float4[]. At ERP scale (thousands of users, not
millions) loading them and scoring in NumPy is far simpler than an index, and
still sub-millisecond. If the population ever outgrows that, the upgrade is
pgvector plus an approximate index — the table shape barely changes.
"""

from __future__ import annotations

import logging
from dataclasses import dataclass

import numpy as np
from psycopg import sql
from psycopg_pool import ConnectionPool

from .config import Settings

logger = logging.getLogger(__name__)


# Embeddings are partitioned by collection. Searching across collections would
# be actively wrong: a person in the lookup registry and a login account can
# share a numeric id while being different people, so an unscoped search could
# answer a login question with a registry hit.
DEFAULT_COLLECTION = "users"


@dataclass(frozen=True)
class StoredFace:
    subject_id: int
    embedding: np.ndarray


class FaceStore:
    def __init__(self, settings: Settings) -> None:
        self._settings = settings
        self._schema = settings.db_schema
        self._pool = ConnectionPool(
            conninfo=settings.database_url,
            min_size=1,
            max_size=4,
            open=False,
            kwargs={"autocommit": True},
        )

    # ------------------------------------------------------------------ #
    # lifecycle
    # ------------------------------------------------------------------ #

    def connect(self) -> None:
        self._pool.open(wait=True, timeout=30)
        self._migrate()

    def close(self) -> None:
        self._pool.close()

    def ping(self) -> bool:
        try:
            with self._pool.connection(timeout=5) as conn:
                conn.execute("SELECT 1")
            return True
        except Exception:
            logger.exception("Database ping failed")
            return False

    def _migrate(self) -> None:
        """Creates the schema and table if they are not there yet."""
        schema = sql.Identifier(self._schema)

        with self._pool.connection() as conn:
            conn.execute(sql.SQL("CREATE SCHEMA IF NOT EXISTS {}").format(schema))
            conn.execute(
                sql.SQL(
                    """
                    CREATE TABLE IF NOT EXISTS {}.embeddings (
                        id          bigserial PRIMARY KEY,
                        user_id     integer      NOT NULL,
                        embedding   float4[]     NOT NULL,
                        model       text         NOT NULL,
                        created_at  timestamptz  NOT NULL DEFAULT now()
                    )
                    """
                ).format(schema)
            )
            # Added after the first release, so existing rows fall back to the
            # collection they were created in.
            # PostgreSQL cannot infer the type of a bound parameter in a
            # DEFAULT clause, so the value is composed as a literal instead.
            conn.execute(
                sql.SQL(
                    "ALTER TABLE {}.embeddings"
                    " ADD COLUMN IF NOT EXISTS collection text NOT NULL DEFAULT {}"
                ).format(schema, sql.Literal(DEFAULT_COLLECTION))
            )
            conn.execute(
                sql.SQL(
                    "CREATE INDEX IF NOT EXISTS embeddings_lookup_idx ON {}.embeddings (collection, user_id)"
                ).format(schema)
            )

        logger.info("Schema %s is ready", self._schema)

    # ------------------------------------------------------------------ #
    # queries
    # ------------------------------------------------------------------ #

    def add(self, subject_id: int, embedding: np.ndarray, model: str, collection: str) -> int:
        with self._pool.connection() as conn:
            row = conn.execute(
                sql.SQL(
                    "INSERT INTO {}.embeddings (user_id, embedding, model, collection)"
                    " VALUES (%s, %s, %s, %s) RETURNING id"
                ).format(sql.Identifier(self._schema)),
                (subject_id, embedding.tolist(), model, collection),
            ).fetchone()

        return int(row[0])

    def all_faces(self, collection: str) -> list[StoredFace]:
        with self._pool.connection() as conn:
            rows = conn.execute(
                sql.SQL(
                    "SELECT user_id, embedding FROM {}.embeddings WHERE collection = %s"
                ).format(sql.Identifier(self._schema)),
                (collection,),
            ).fetchall()

        return [
            StoredFace(subject_id=int(sid), embedding=np.asarray(embedding, dtype=np.float32))
            for sid, embedding in rows
        ]

    def faces_for(self, subject_id: int, collection: str) -> list[StoredFace]:
        with self._pool.connection() as conn:
            rows = conn.execute(
                sql.SQL(
                    "SELECT user_id, embedding FROM {}.embeddings"
                    " WHERE user_id = %s AND collection = %s"
                ).format(sql.Identifier(self._schema)),
                (subject_id, collection),
            ).fetchall()

        return [
            StoredFace(subject_id=int(sid), embedding=np.asarray(embedding, dtype=np.float32))
            for sid, embedding in rows
        ]

    def count_for(self, subject_id: int, collection: str) -> int:
        with self._pool.connection() as conn:
            row = conn.execute(
                sql.SQL(
                    "SELECT count(*) FROM {}.embeddings WHERE user_id = %s AND collection = %s"
                ).format(sql.Identifier(self._schema)),
                (subject_id, collection),
            ).fetchone()

        return int(row[0])

    def enrolled_ids(self, collection: str) -> list[int]:
        with self._pool.connection() as conn:
            rows = conn.execute(
                sql.SQL(
                    "SELECT DISTINCT user_id FROM {}.embeddings WHERE collection = %s ORDER BY user_id"
                ).format(sql.Identifier(self._schema)),
                (collection,),
            ).fetchall()

        return [int(row[0]) for row in rows]

    def delete_for(self, subject_id: int, collection: str) -> int:
        """Removes every enrolment for a subject. Biometric data must be erasable."""
        with self._pool.connection() as conn:
            cursor = conn.execute(
                sql.SQL(
                    "DELETE FROM {}.embeddings WHERE user_id = %s AND collection = %s"
                ).format(sql.Identifier(self._schema)),
                (subject_id, collection),
            )

        return cursor.rowcount
