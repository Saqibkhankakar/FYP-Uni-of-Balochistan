<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/migrations.php';
try { migrate_database(); echo "Migrations complete: doctor archive column and chatbot knowledge bank available. Existing records and knowledge edits preserved.\n"; }
catch (Throwable $e) { fwrite(STDERR, $e->getMessage() . "\n"); exit(1); }
