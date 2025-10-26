<?php
namespace Deployer;

require 'recipe/typo3.php';

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
// Zusätzliche projektspezifische Konfiguration
// ---------------------------------------------------------
add('shared_dirs', [
    'config/sites',
]);

add('shared_files', [
    'config/system/additional.php',
    'public/.htaccess',
    'public/.user.ini',
]);

// ---------------------------------------------------------
// Host-Konfiguration
// ---------------------------------------------------------
host('live')
    ->set('hostname', getenv('DEPLOY_HOST') ?: 'example.com')
    ->set('remote_user', getenv('DEPLOY_SSH_USER') ?: 'deployer')
    ->set('deploy_path', getenv('DEPLOY_PATH') ?: '/var/www/html');

// ---------------------------------------------------------
// Dateiberechtigungen setzen (mit Fehler-Toleranz)
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
// Zusätzliche Hooks für projektspezifische Tasks
// ---------------------------------------------------------
after('deploy:prepare', 'fix:permissions');
after('deploy:symlink', 'fix:permissions');
after('deploy:success', 'fix:permissions');
