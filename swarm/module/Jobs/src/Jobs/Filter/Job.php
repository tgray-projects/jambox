<?php
/**
 * Perforce Swarm
 *
 * @copyright   2014 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 * 
 * Current spec:
 * Job:           The job name. 'new' generates a sequenced job number.
 * Status:        Job status; [open/closed/suspended].  Required
 * Project:       The project this job is for. Required.
 * Severity:      [A/B/C] (A is highest)  Required.
 * ReportedBy     The user who created the job. Can be changed.
 * ReportedDate:  The date the job was created.  Automatic.
 * ModifiedBy:    The user who last modified this job. Automatic.
 * ModifiedDate:  The date this job was last modified. Automatic.
 * OwnedBy:       The owner, responsible for doing the job. Optional.
 * Description:   Description of the job.  Required.
 * DevNotes:      Developer's comments.  Optional.
 * Type:	      Type of job; [Bug/Feature].  Required.
 */

namespace Jobs\Filter;

use Projects\Model\Project as ProjectModel;
use P4\Connection\ConnectionInterface as Connection;
use Users\Model\User;
use Zend\InputFilter\InputFilter;

class Job extends InputFilter
{
    const MODE_ADD  = 'add';
    const MODE_EDIT = 'edit';

    protected $mode;

    /**
     * Extends parent to add all of the job filters and setup the p4 connection.
     *
     * @param   Connection  $p4     connection to use for validation
     */
    public function __construct(Connection $p4)
    {
        // prepare callback to validate users (as its used on multiple elements)
        $usersValidatorCallback = function ($value) use ($p4) {
            if (!is_string($value)) {
                return 'User id must be a string';
            }

            return User::exists($value, $p4);
        };

        // declare name, value 'new' indicates new job to be created
        $filter = $this;
        $this->add(
            array(
                'name'          => 'job',
                'filters'       => array(
                    array('name' => 'StringTrim'),
                ),
                'validators'    => array(
                    array(
                        'name'      => '\Application\Validator\Callback',
                        'options'   => array(
                            'callback'  => function ($value) use ($filter) {
                                // if it isn't an add, we assume the caller will take care
                                // of ensuring existence.
                                if ($filter->getMode() !== $filter::MODE_ADD) {
                                    return true;
                                }

                                if ($value !== 'new') {
                                    return 'Name for new jobs must be "new".';
                                }

                                return true;
                            }
                        )
                    ),
                    array(
                        'name'      => 'NotEmpty',
                        'options'   => array(
                            'message'   =>  "Job name is required and can't be empty."
                        )
                    ),
                )
            )
        );

        $this->add(
            array(
                'name'       => 'status',
                'validators' => array(
                    array(
                        'name'    => 'inArray',
                        'options' => array(
                            'haystack' => array('open', 'inprogress', 'block', 'suspended', 'closed')
                        ),
                    ),
                ),
            )
        );

        $this->add(
            array(
                'name'          => 'severity',
                'validators'    => array(
                    array(
                        'name'    => 'inArray',
                        'options' => array(
                            'haystack' => array('A', 'B', 'C')
                        ),
                    ),
                ),
            )
        );

        $this->add(
            array(
                'name'          => 'ownedBy',
                'validators'    => array(
                    array(
                        'name'      => '\Application\Validator\Callback',
                        'options'   => array(
                            'callback'  => $usersValidatorCallback
                        )
                    )
                )
            )
        );



        $this->add(
            array(
                'name'      => 'project',
                'filters'   => array('trim'),
            )
        );

        $this->add(
            array(
                'name'          => 'description',
                'filters'       => array('trim'),
                'validators'    => array(
                    array(
                        'name'      => 'NotEmpty',
                        'options'   => array(
                            'message'   => 'Description must not be empty.'
                        )
                    ),
                )
            )
        );

        $this->add(
            array(
                'name'      => 'devNotes',
                'filters'   => array('trim'),
                'required'  => false,
            )
        );

        $this->add(
            array(
                'name'          => 'type',
                'validators'    => array(
                    array(
                        'name'    => 'inArray',
                        'options' => array(
                            'haystack' => array('Bug', 'Feature')
                        ),
                    ),
                ),
            )
        );
    }

    /**
     * Set the mode to influence id validation (add vs. edit)
     * When mode is set to 'add', the filter will ensure the id is unique.
     *
     * @param   string      $mode   'add' or 'edit'
     * @return  Project     provides fluent interface
     * @throws  \InvalidArgumentException
     */
    public function setMode($mode)
    {
        if ($mode !== static::MODE_ADD && $mode !== static::MODE_EDIT) {
            throw new \InvalidArgumentException('Invalid mode specified for project add/edit.');
        }

        $this->mode = $mode;

        return $this;
    }

    /**
     * Get the current mode (add or edit)
     *
     * @return  string  'add' or 'edit'
     * @throws  \RuntimeException   if mode has not been set
     */
    public function getMode()
    {
        if (!$this->mode) {
            throw new \RuntimeException("Cannot get mode. No mode has been set.");
        }

        return $this->mode;
    }
}
