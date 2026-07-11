<?php

declare(strict_types=1);

const APP_NAME = 'מערכת ניהול מחסן FRC';
const APP_ENV = 'development';
const APP_URL = 'YOUR URL';

const DB_HOST = 'localhost';
const DB_NAME = 'DBNAME';
const DB_USER = 'DBUSER';
const DB_PASS = 'DBPASS';
const DB_CHARSET = 'utf8mb4';

const SESSION_TIMEOUT = 3600;
const REMEMBER_ME_DAYS = 30;

const MAX_UPLOAD_SIZE = 5 * 1024 * 1024;

const ALLOWED_IMAGE_TYPES = [
    'image/jpeg',
    'image/png',
    'image/webp'
];

date_default_timezone_set('Asia/Jerusalem');

if (APP_ENV === 'development') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
}