<?php
namespace Deployer;

// ---------------------------------------------------------
// TYPO3 Basis-Recipe laden (liefert Standard-Tasks)
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
set('ssh_private_key', getenv('DEPLOY_SSH_KEY'));
set('allow_anonymous_stats', false);
set('keep_releases', 5);

// ---------------------------------------------------------
// Shared + Writable (persistente Dateien & Ordner)
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
// Ziel-Host Konfiguration
// ---------------------------------------------------------
host('live')
    ->set('hostname', getenv('DEPLOY_HOST') ?: 'example.com')
    ->set('remote_user', getenv('DEPLOY_SSH_USER') ?: 'deploy')
    ->set('deploy_path', getenv('DEPLOY_PATH') ?: '/var/www/maidem.de');

// ---------------------------------------------------------
// TYPO3 Cache leeren (eigener Task, da kein offizieller vorhanden)
// ---------------------------------------------------------
desc('Flush TYPO3 cache');
task('typo3:cache:flush', function () {
    run('{{bin/php}} {{release_path}}/vendor/bin/typo3 cache:flush || true');
});

// ---------------------------------------------------------
// Dateiberechtigungen setzen (robust gegen Fehler)
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
// Hooks (Reihenfolge der Tasks)
// ---------------------------------------------------------
after('deploy:prepare', 'fix:permissions');
after('deploy:vendors', 'fix:permissions');
after('deploy:symlink', 'typo3:cache:flush');
after('deploy:symlink', 'fix:permissions');
after('deploy:success', 'fix:permissions');
after('deploy:failed', 'deploy:unlock');

// ---------------------------------------------------------
// Rollback-Task (schnelles Zurückrollen auf vorheriges Release)
// ---------------------------------------------------------
desc('Rollback to previous release');
task('rollback', function () {
    run('cd {{deploy_path}} && ln -nfs $(ls -td releases/* | sed -n 2p) current');
    invoke('typo3:cache:flush');
    invoke('fix:permissions');
});