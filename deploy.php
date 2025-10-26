<?php
namespace Deployer;

// ---------------------------------------------------------
// Basis-Recipe (Deployer-Standard)
// ---------------------------------------------------------
require 'recipe/common.php';

// ---------------------------------------------------------
// Projekt-Konfiguration
// ---------------------------------------------------------
set('application', 'portfolio-v1');
set('repository', 'git@github.com:maidem/portfolio-v1.git');
set('branch', function () {
    return getenv('DEPLOY_BRANCH') ?: 'main';
});

set('bin/php', '/usr/bin/php8.3');
set('allow_anonymous_stats', false);
set('keep_releases', 5);

// ---------------------------------------------------------
// Shared + Writable Dateien / Ordner
// ---------------------------------------------------------
set('shared_dirs', [
    'config/sites',
    'public/fileadmin',
    'public/uploads',
    'public/typo3temp',
    'var',
]);

set('shared_files', [
    '.env',
    'config/system/additional.php',
    'public/.htaccess',
    'public/.user.ini',
]);

set('writable_dirs', [
    'public/fileadmin',
    'public/uploads',
    'public/typo3temp',
    'var',
]);

// ---------------------------------------------------------
// Ziel-Host
// ---------------------------------------------------------
host('live')
    ->set('hostname', getenv('DEPLOY_HOST'))
    ->set('remote_user', getenv('DEPLOY_SSH_USER'))
    ->set('deploy_path', getenv('DEPLOY_PATH') ?: '/var/www/maidem.de');

// ---------------------------------------------------------
// Composer installieren (nach Code-Update)
// ---------------------------------------------------------
desc('Install composer dependencies');
task('deploy:composer', function () {
    run('{{bin/php}} {{release_path}}/composer.phar install --no-dev --prefer-dist --no-interaction');
})->once();

// ---------------------------------------------------------
// TYPO3 Cache leeren (eigener Task)
// ---------------------------------------------------------
desc('Flush TYPO3 cache');
task('typo3:cache:flush', function () {
    run('{{bin/php}} {{release_path}}/vendor/bin/typo3 cache:flush || true');
});

// ---------------------------------------------------------
// Berechtigungen setzen
// ---------------------------------------------------------
desc('Set correct permissions');
task('fix:permissions', function () {
    run('find {{release_path}} -type d -exec chmod 2770 {} + || true');
    run('find {{release_path}} -type f -exec chmod 0660 {} + || true');

    $sharedDirs = [
        '{{deploy_path}}/shared/public/fileadmin',
        '{{deploy_path}}/shared/public/uploads',
        '{{deploy_path}}/shared/public/typo3temp',
        '{{deploy_path}}/shared/var',
    ];

    foreach ($sharedDirs as $dir) {
        run("if [ -d $dir ]; then find $dir -type d -exec chmod 2770 {} + || true; find $dir -type f -exec chmod 0660 {} + || true; fi");
    }

    writeln('<info>Permissions fixed (non-critical chmod errors ignored).</info>');
});

// ---------------------------------------------------------
// Automatische Aufgaben-Reihenfolge (Hooks)
// ---------------------------------------------------------
after('deploy:update_code', 'deploy:composer');   // Composer nach Code-Update
after('deploy:shared', 'fix:permissions');        // Rechte nach shared-Files
after('deploy:symlink', 'typo3:cache:flush');     // Cache nach Symlink
after('deploy:symlink', 'fix:permissions');       // Rechte final prüfen
after('deploy:success', 'fix:permissions');       // Rechte nach Erfolg
after('deploy:failed', 'deploy:unlock');          // Unlock bei Fehler

// ---------------------------------------------------------
// Rollback
// ---------------------------------------------------------
desc('Rollback to previous release');
task('rollback', function () {
    run('cd {{deploy_path}} && ln -nfs $(ls -td releases/* | sed -n 2p) current');
    invoke('typo3:cache:flush');
    invoke('fix:permissions');
});