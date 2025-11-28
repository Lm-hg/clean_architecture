<?php
declare(strict_types=1);

// Minimal idempotent seeder for integration tests.
// Uses environment variables: DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD

function env(string $k, $default = null) {
    $v = getenv($k);
    return $v === false ? $default : $v;
}

$host = env('DB_HOST', 'localhost');
$port = env('DB_PORT', '5432');
$db = env('DB_NAME', 'parking_db_test');
$user = env('DB_USER', 'parking_user');
$pass = env('DB_PASSWORD', 'test_password');

$dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $db);

echo "Seeder: connecting to Postgres at $host:$port/$db...\n";
try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Throwable $e) {
    fwrite(STDERR, "Seeder: cannot connect to Postgres: " . $e->getMessage() . "\n");
    // Don't fail CI hard here; tests will skip if DB unavailable
    exit(0);
}

function tableExists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("SELECT to_regclass(:t)");
    $stmt->execute([':t' => $table]);
    $res = $stmt->fetchColumn();
    return $res !== null && $res !== '';
}

// If full init.sql exists, try to apply it via psql (if available)
$initPath = __DIR__ . '/../src/docker/postgres/init.sql';
if ((!tableExists($pdo, 'users') || !tableExists($pdo, 'parkings')) && file_exists($initPath)) {
    echo "Seeder: init.sql found; attempting to apply via psql if available...\n";
    $which = trim((string) shell_exec('which psql 2>/dev/null'));
    if ($which !== '') {
        // Provide password to psql non-interactively to avoid password prompt in CI
        $pgpass = escapeshellarg($pass);
        $cmd = sprintf('PGPASSWORD=%s psql -h %s -U %s -d %s -f %s', $pgpass, escapeshellarg($host), escapeshellarg($user), escapeshellarg($db), escapeshellarg($initPath));
        echo "Seeder: running: $cmd\n";
        passthru($cmd, $rc);
        if ($rc !== 0) {
            fwrite(STDERR, "Seeder: psql returned non-zero code: $rc\n");
        }
    } else {
        echo "Seeder: psql not available in PATH; skipping init.sql apply\n";
    }
}

// Ensure at least one user exists
try {
    $count = (int) $pdo->query('SELECT count(*) FROM users')->fetchColumn();
} catch (Throwable $e) {
    fwrite(STDERR, "Seeder: cannot query users table: " . $e->getMessage() . "\n");
    exit(0);
}

if ($count === 0) {
    echo "Seeder: inserting a minimal test user...\n";
    $email = 'seed+ci+' . time() . '@example.com';
    $id = bin2hex(random_bytes(8));
    $password = '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
    $sql = 'INSERT INTO users (id, role, first_name, name, email, password, created_at, updated_at) VALUES (:id, :role, :first_name, :name, :email, :password, now(), now()) ON CONFLICT (email) DO NOTHING';
    $stmt = $pdo->prepare($sql);
    try {
        $stmt->execute([':id'=>$id, ':role'=>'user', ':first_name'=>'Seed', ':name'=>'User', ':email'=>$email, ':password'=>$password]);
        echo "Seeder: user inserted (email=$email)\n";
    } catch (Throwable $e) {
        fwrite(STDERR, "Seeder: failed to insert user: " . $e->getMessage() . "\n");
    }
}

// Ensure at least one parking exists
try {
    $countP = (int) $pdo->query('SELECT count(*) FROM parkings')->fetchColumn();
} catch (Throwable $e) {
    fwrite(STDERR, "Seeder: cannot query parkings table: " . $e->getMessage() . "\n");
    exit(0);
}

if ($countP === 0) {
    echo "Seeder: inserting a minimal parking...\n";
    // find any user to be owner
    $owner = $pdo->query('SELECT id FROM users LIMIT 1')->fetchColumn();
    if (!$owner) {
        $owner = bin2hex(random_bytes(8));
        // attempt to insert owner if somehow missing
        $pdo->prepare('INSERT INTO users (id, role, first_name, name, email, password, created_at, updated_at) VALUES (:id, :role, :first_name, :name, :email, :password, now(), now()) ON CONFLICT (email) DO NOTHING')
            ->execute([':id'=>$owner, ':role'=>'ownerParking', ':first_name'=>'SeedOwner', ':name'=>'Owner', ':email'=>'seed+owner+' . time() . '@example.com', ':password'=>$password]);
    }

    $sqlP = 'INSERT INTO parkings (owner_id, title, description, address, city, postal_code, latitude, longitude, price_per_hour, total_spots, available_spots, created_at, updated_at) VALUES (:owner_id, :title, :description, :address, :city, :postal_code, :latitude, :longitude, :price_per_hour, :total_spots, :available_spots, now(), now())';
    $stmt = $pdo->prepare($sqlP);
    try {
        $stmt->execute([':owner_id'=>$owner, ':title'=>'Seed Parking', ':description'=>'Seeded by CI', ':address'=>'Test Address', ':city'=>'TestCity', ':postal_code'=>'00000', ':latitude'=>48.8566, ':longitude'=>2.3522, ':price_per_hour'=>5.00, ':total_spots'=>1, ':available_spots'=>1]);
        echo "Seeder: parking inserted\n";
    } catch (Throwable $e) {
        fwrite(STDERR, "Seeder: failed to insert parking: " . $e->getMessage() . "\n");
    }
}

echo "Seeder: done\n";
exit(0);
