<?php

// Ambil konfigurasi dari Environment Variables Vercel/.env
$host     = getenv('DB_HOST')     ?: 'db.xxxxxx.supabase.co'; // Ganti dengan Host Supabase Anda
$user     = getenv('DB_USERNAME') ?: 'postgres';
$pass     = getenv('DB_PASSWORD') ?: 'password_database_supabase_anda';
$dbname   = getenv('DB_DATABASE') ?: 'postgres';
$port     = getenv('DB_PORT')     ?: 5432;

// Jika Supabase Anda menggunakan PostgreSQL (Standard Supabase)
$conn_string = "host={$host} port={$port} dbname={$dbname} user={$user} password={$pass}";
$db = pg_connect($conn_string);

if (!$db) {
    die("Koneksi database Supabase gagal.");
}
