<?php defined('BASEPATH') or exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Fork build identity
|--------------------------------------------------------------------------
|
| Overwritten at Docker image build time (see docker/production/Dockerfile).
| Local/dev defaults stay as "dev" so missing bake still boots cleanly.
|
*/

$config['build_id'] = 'dev';
$config['build_commit'] = 'local';
$config['build_branch'] = 'local';
$config['build_time'] = '';
