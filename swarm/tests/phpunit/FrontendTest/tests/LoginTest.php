<?php

namespace Tests;

use \Tests\SwarmTest;
use \Pages\LoginPage;

class LoginTest extends SwarmTest
{
    public function setUp()
    {
        $this->tags = $this->tags + array('login');

        parent::setUp();
    }

    public function setUpPage() {
        $this->login = new LoginPage($this);

        // note that start_url is used by the webdriver code, so while this
        // line appears superfluous, it's not.
        $this->start_url = $this->login->url;
        $this->setBrowserUrl($this->start_url);
        $this->login->open()->validate();
    }

    public function testLogin()
    {
        // verify vera can log into swarm
        $this->login->openLoginDialog();
        $this->login->username = $this->p4users['vera']['User'];
        $this->login->password = $this->p4users['vera']['Password'];
        $this->login->submitLoginDialog();
    }
}