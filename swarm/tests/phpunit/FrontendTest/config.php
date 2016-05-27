<?php
    $_config = array(
        'localOnly' => true,
        'browsers'  => array(
            // run FF15 on Windows 8 on Sauce
//            array(
//                'browserName' => 'firefox',
//                'desiredCapabilities' => array(
//                    'version' => '15',
//                    'platform' => 'Windows 2012',
//                )
//            ),
//            // run Chrome on Linux on Sauce
//            array(
//                'browserName' => 'chrome',
//                'desiredCapabilities' => array(
//                    'platform' => 'Linux'
//              )
//            ),
            // run Chrome locally
            array(
                'browserName' => 'chrome',
                'local' => true,
                'sessionStrategy' => 'shared'
            )
        )
    );