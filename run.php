<?php
/* (c) Anton Medvedev <anton@elfet.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
/** @var $autoload \Composer\Autoload\ClassLoader */
$autoload = require_once __DIR__ . '/vendor/autoload.php';
$autoload->add('Fixture', __DIR__ . '/test/');
echo "==============================================================\n\n";

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Query\QueryBuilder;
use Fixture\Profile;
use Fixture\User;
use Granula\EntityManager;
use Granula\EventManager;
use Granula\Mapper\ResultMapper;

$evm = new EventManager();
$evm->addEventListener(EntityManager\Events::preUpdateSchema, function () {
    if (!empty($sql = EntityManager::getInstance()->getSchemaTool()->getUpdateSchemaSql())) {
        echo "UPDATE SCHEMA:\n";
        var_dump($sql);
    }
});

$params = [
    'dev' => true,
    'event_manager' => $evm,
    'sql_logger' => function ($sql, $params = []) {
            echo "• $sql · " . json_encode($params) . "\n";
        },

    'driver' => 'pdo_mysql',
    'host' => 'localhost',
    'user' => 'root',
    'password' => '',
    'dbname' => 'granula',
    'charset' => 'utf8'
];

$em = new EntityManager($params, [
    User::class,
    Profile::class,
]);

$user = User::find(1);
print_r($user);

//$result = User::query('SELECT * FROM users u WHERE u.id IN (?)', [[1, 2]], [Connection::PARAM_INT_ARRAY]);
//
//foreach ($result as $user) {
//    var_dump($user);
//}

//$profile = new Profile();
//$profile->user_id = 12344;
//$profile->create();
//
//$user = new User();
//$user->setName('Elfet');
//$user->setEmail('elfet@medvedev.be');
//$user->setPassword('1234');
//$user->setAvatar(null);
//$user->profile = $profile;
//$user->date = new DateTime('now');
//$user->create();
//var_dump($user);