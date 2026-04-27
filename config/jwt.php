<?php

return [
    /*
     * RSA public key from User Service used to verify RS256 JWT tokens.
     * Set USER_SERVICE_PUBLIC_KEY in your .env (PEM format).
     */
    'public_key' => env('USER_SERVICE_PUBLIC_KEY', ''),
];
