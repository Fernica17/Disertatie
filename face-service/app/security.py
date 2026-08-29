"""Shared-secret guard.

The service is meant to sit on the private Docker network, unreachable from
outside. The API key is a second line of defence, not the first: it stops
another container on the same network from enrolling faces.
"""

import hmac

from fastapi import Header, HTTPException, status

from .config import get_settings


async def require_api_key(x_api_key: str = Header(default="")) -> None:
    expected = get_settings().api_key

    # compare_digest keeps the check constant-time.
    if not hmac.compare_digest(x_api_key, expected):
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid or missing X-API-Key header",
        )
