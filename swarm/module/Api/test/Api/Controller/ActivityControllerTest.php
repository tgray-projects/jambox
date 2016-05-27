<?php
/**
 * Perforce Swarm
 *
 * @copyright   2014 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace ApiTest\Controller;

use ModuleTest\TestControllerCase;
use P4\File\File;
use P4\Spec\Job;
use Zend\Http\Request;
use Zend\Json\Json;
use Zend\Stdlib\Parameters;

class ActivityControllerTest extends TestControllerCase
{
    public function testActivityCreate()
    {
        // switch to an admin user to get past ACL
        $services = $this->getApplication()->getServiceManager();
        $services->setService('p4_user', $services->get('p4_admin'));

        $activity = array(
            'type'   => 'coffee',
            'user'   => 'A dingo',
            'action' => 'ate my',
            'target' => 'baby'
        );

        $this->post('/api/v1/activity', $activity);
        $actual   = json_decode($this->getResponse()->getContent(), true);
        $expected = array(
            'activity' => array(
                'id'            => 1,
                'action'        => 'ate my',
                'behalfOf'      => null,
                'change'        => null,
                'depotFile'     => null,
                'description'   => '',
                'details'       => array(),
                'followers'     => array(),
                'link'          => '',
                'preposition'   => 'for',
                'projects'      => array(),
                'streams'       => array(),
                'target'        => 'baby',
                'topic'         => '',
                'type'          => 'coffee',
                'user'          => 'A dingo'
            )
        );

        unset($actual['activity']['time']);

        $this->assertSame(200, $this->getResponse()->getStatusCode());
        $this->assertSame($expected, $actual);
    }

    public function testActivityCreateForbidden()
    {
        $activity = array(
            'type'   => 'coffee',
            'user'   => 'A dingo',
            'action' => 'ate my',
            'target' => 'baby'
        );

        $this->post('/api/v1/activity', $activity);
        $actual   = json_decode($this->getResponse()->getContent(), true);
        $expected = array('error' => 'Forbidden');

        $this->assertSame(403, $this->getResponse()->getStatusCode());
        $this->assertSame($expected, $actual);
    }

    protected function post($path, $params)
    {
        $post = $params instanceof Parameters ? $params : new Parameters($params);
        $this->getRequest()
            ->setMethod(Request::METHOD_POST)
            ->setPost($post);

        $this->dispatch($path);

        return $this->getResponse();
    }
}
