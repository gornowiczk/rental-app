<?php
declare(strict_types=1);

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

require __DIR__ . '/vendor/autoload.php';

$env = $_SERVER['APP_ENV'] ?? 'dev';
$debug = (bool)($_SERVER['APP_DEBUG'] ?? true);

$kernel = new \App\Kernel($env, $debug);
$kernel->boot();

$container = $kernel->getContainer();

/** @var EntityManagerInterface $em */
$em = $container->get('doctrine')->getManager();
/** @var UserPasswordHasherInterface $hasher */
$hasher = $container->get(UserPasswordHasherInterface::class);

$email = $argv[1] ?? null;
$plainPassword = $argv[2] ?? null;

if (!$email || !$plainPassword) {
    fwrite(STDERR, "Użycie: php fix_admin.php email haslo\n");
    exit(1);
}

$userRepo = $em->getRepository(User::class);
/** @var User|null $user */
$user = $userRepo->findOneBy(['email' => $email]);

if (!$user) {
    $user = new User();
    if (method_exists($user, 'setEmail')) {
        $user->setEmail($email);
    } else {
        fwrite(STDERR, "Brak setEmail() w encji User.\n");
        exit(2);
    }
    $em->persist($user);
}

$roles = $user->getRoles();
if (!in_array('ROLE_USER', $roles, true)) $roles[] = 'ROLE_USER';
if (!in_array('ROLE_ADMIN', $roles, true)) $roles[] = 'ROLE_ADMIN';
$user->setRoles($roles);

$user->setPassword($hasher->hashPassword($user, $plainPassword));

// Pola statusu konta są ustawiane automatycznie, jeśli występują w encji.
$boolSetters = [
    'setIsVerified' => true,
    'setVerified' => true,
    'setEnabled' => true,
    'setIsActive' => true,
    'setActive' => true,
];
foreach ($boolSetters as $method => $value) {
    if (method_exists($user, $method)) {
        $user->{$method}($value);
    }
}
if (method_exists($user, 'setVerifiedAt')) {
    $user->setVerifiedAt(new \DateTimeImmutable());
}

$em->flush();

echo "OK: ustawiono/utworzono admina: {$email}\n";
