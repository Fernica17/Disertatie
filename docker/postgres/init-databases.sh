#!/bin/bash
# Creates the side databases owned by services other than the Symfony app:
#   - audit: the second Doctrine entity manager
#   - face:  the Python face service
# Kept out of the main database so neither shows up in Doctrine's introspection.
set -e
psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-SQL
    CREATE DATABASE ${POSTGRES_AUDIT_DB:-erp_audit} OWNER ${POSTGRES_USER};
    CREATE DATABASE ${POSTGRES_FACE_DB:-erp_face} OWNER ${POSTGRES_USER};
SQL
