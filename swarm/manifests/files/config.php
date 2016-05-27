<?php
    return array(
        'p4' => array(
            'port'      => '1666',
            'user'      => 'bruno',
            'password'  => 'fooBARbaz'
        ),
        'log' => array(
            'priority'  => 5 // 7 for max
        ),
        'mail' => array(
            'transport' => array(
                'path' => '/vagrant/logs/email'
            )
        ),
        'environment'   => array(
            'mode'  => 'development'
        ),
    );
