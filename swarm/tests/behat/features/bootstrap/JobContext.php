<?php
/**
 * Perforce Swarm
 *
 * @copyright   2015 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

 namespace BehatTests;

 use P4\Spec\Job;

class JobContext extends AbstractContext
{

    /**
     * This function creates a job on the Perforce server using p4php.
     *
     * @Given /^create a job$/
     */
    public function createAJob()
    {
        $connection = $this->getP4Context()->getAdminUserConnection();

        $job = new Job;
        $job->setDescription("This is a job from Behat");
        $job->save();

        $jobs = $job->fetchAll();
    }
}
