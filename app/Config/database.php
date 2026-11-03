<?php
// Ganti host, username, password, dan nama database dengan data Supabase Anda
$host     = getenv('DB_HOST')     ?: 'db.xxxxxx.supabase.co'; 
$user     = getenv('DB_USERNAME') ?: 'postgres';
$pass     = getenv('DB_PASSWORD') ?: 'password_database_supabase_anda';
$dbname   = getenv('DB_DATABASE') ?: 'postgres';
$port     = getenv('DB_PORT')     ?: 5432;

// Koneksi ke Supabase PostgreSQL
$conn_string = "host={$host} port={$port} dbname={$dbname} user={$user} password={$pass}";
$db = pg_connect($conn_string);

if (!$db) {
    die("Koneksi database Supabase gagal.");
}
