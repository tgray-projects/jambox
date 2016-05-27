<?php

require_once('../../library/P4/Time/Time.php');
require_once('../../library/P4/Connection/Connection.php');
require_once('../../library/P4/Connection/ConnectionInterface.php');
require_once('../../library/P4/Connection/AbstractConnection.php');
require_once('../../library/P4/Connection/Extension.php');
require_once('../../library/P4/Exception.php');
require_once('../../library/P4/Validate/ValidateInterface.php');
require_once('../../library/P4/Validate/AbstractValidate.php');
require_once('../../library/P4/Validate/KeyName.php');
require_once('../../library/P4/Validate/CounterName.php');
require_once('../../library/P4/Model/Connected/ConnectedInterface.php');
require_once('../../library/P4/Model/Connected/ConnectedAbstract.php');
require_once('../../library/P4/OutputHandler/Limit.php');
require_once('../../library/P4/Model/Connected/Iterator.php');
require_once('../../library/Record/Exception/Exception.php');
require_once('../../library/Record/Exception/NotFoundException.php');
require_once('../../library/P4/Counter/Exception/NotFoundException.php');
require_once('../../library/P4/Counter/AbstractCounter.php');
require_once('../../library/P4/Key/Key.php');
require_once('../../library/P4/Connection/Exception/ServiceNotFoundException.php');
require_once('../../library/P4/Connection/CommandResult.php');
require_once('../../library/P4/Log/Logger.php');
require_once('../../library/P4/Environment/Environment.php');
require_once('../../library/P4/Model/Fielded/FieldedInterface.php');
require_once('../../library/P4/Model/Fielded/FieldedAbstract.php');
require_once('../../library/P4/Spec/SingularAbstract.php');
require_once('../../library/P4/Spec/PluralAbstract.php');
require_once('../../library/P4/Spec/Client.php');
require_once('../../library/P4/Spec/Group.php');
require_once('../../library/P4/Spec/User.php');
require_once('../../library/P4/Validate/SpecName.php');
require_once('../../library/P4/Validate/UserName.php');
require_once('../../library/P4/Model/Fielded/Iterator.php');
require_once('../../library/P4/Filter/Utf8.php');
require_once('../../library/Record/Key/AbstractKey.php');
require_once('../../module/Projects/src/Projects/Model/Project.php');
require_once('../../module/Users/src/Users/Model/Config.php');
require_once('../../module/Users/src/Users/Model/Group.php');
require_once('../../module/Users/src/USers/Model/User.php');

$p4port = 'localhost:1666';
$p4user = 'llam';

// Check to see user passed 2 arguments
if (!isset($argv[2])) {
    echo "Usage: swarm-move-followers.php [source project id] [target project id]";
    return;
}

// Make a Perforce connection
try {
    $connection = new \P4\Connection\Extension($p4port, $p4user, null, null, null);
    $connection->connect();
} catch (\Exception $e) {
}

$projects = new \Projects\Model\Project;

// Check if source project exists
// Note: project id has the form $username-$projectname
if(!$projects->exists($argv[1], $connection)) {
    echo $argv[1] . " project does not exist.";
    return;
};

// Check if target project exists
if(!$projects->exists($argv[2], $connection)) {
    echo $argv[2] . " project does not exist.";
    return;
};

// fetch followers from source project
$followers = \Users\Model\Config::fetchFollowerIds(
    $argv[1],
    'project',
    $connection
);

\P4\Connection\Connection::setDefaultConnection($connection);

foreach ($followers as $follower) {
    $user = new \Users\Model\User();
    $user->setId($follower);
    $user->setConnection($connection);
    $config = $user->getConfig();
    $config->addFollow($argv[2], 'project');
    $config->save();
}

