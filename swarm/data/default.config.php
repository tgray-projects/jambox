<?php
        return array(
            'p4' => array(
                'port'     => 'localhost:1666',
                'user'     => 'swarm-user',
                'password' => 'ADMIN_TICKET',
            ),
            'p4_super' => array(
                'port'     => 'localhost:1666',
                'user'     => 'perforce',
                'password' => 'SUPER_TICKET',
            ),
            'mail' => array(
                'transport' => array('host' => 'localhost'),
                'sender'    => 'jambox@perforce.com',
                'use_bcc'   => true,
                'subject_prefix' => '[Jambox]',
            ),
            'security' => array(
                'require_login' => true,
                'disable_autojoin' => true
            ),
            'notifications' => array(
                'honor_p4_reviews'      => true,
            ),
            'environment' => array(
                'hostname' => 'jambox'
            ),
            'reviews' => array(
                'disable_commit' => false,
            ),
            'activity' => array(
                'ignored_users' => array(
                    'swarm-user'
                )
            )
        );
