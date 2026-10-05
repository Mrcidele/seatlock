SELECT 'CREATE DATABASE seatlock_testing'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'seatlock_testing')\gexec
