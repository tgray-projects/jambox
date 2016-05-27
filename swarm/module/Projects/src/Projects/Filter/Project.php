<?php
/**
 * Perforce Swarm
 *
 * @copyright   2013 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace Projects\Filter;

use Application\Filter\StringToId;
use Application\Validator\Callback as CallbackValidator;
use P4\Connection\ConnectionInterface as Connection;
use Projects\Model\Project as ProjectModel;
use Projects\Validator\BranchPath as BranchPathValidator;
use Users\Model\User;
use Zend\InputFilter\InputFilter;
use Zend\Validator\ValidatorChain;

class Project extends InputFilter
{
    const MODE_ADD  = 'add';
    const MODE_EDIT = 'edit';

    protected $mode;

    /**
     * Extends parent to add all of the project filters and setup the p4 connection.
     *
     * @param   Connection  $p4     connection to use for validation
     */
    public function __construct(Connection $p4)
    {
        $toId       = new StringToId;
        $reserved   = array('add', 'edit', 'delete');
        $translator = $p4->getService('translator');

        // prepare callback to validate users (as its used on multiple elements)
        $usersValidatorCallback = function ($value) use ($p4, $translator) {
            if (in_array(false, array_map('is_string', $value))) {
                return 'User ids must be strings';
            }

            $unknownIds = array_diff($value, User::exists($value, $p4));
            if (count($unknownIds)) {
                return $translator->tp(
                    'Unknown user id %s',
                    'Unknown user ids %s',
                    count($unknownIds),
                    array(implode(', ', $unknownIds))
                );
            }

            return true;
        };

        // declare id, but make it optional and rely on name validation.
        // you can place the 'name' into id for adds and it will auto-filter it.
        $this->add(
            array(
                 'name'      => 'id',
                 'required'  => false,
                 'filters'   => array($toId)
            )
        );

        // ensure name is given and produces a usable/unique id.
        $filter = $this;
        $this->add(
            array(
                 'name'          => 'name',
                 'filters'       => array('trim'),
                 'validators'    => array(
                     array(
                         'name'      => 'NotEmpty',
                         'options'   => array(
                             'message' => "Name is required and can't be empty."
                         )
                     ),
                     array(
                         'name'      => '\Application\Validator\Callback',
                         'options'   => array(
                             'callback' => function ($value) use ($p4, $toId, $reserved, $filter) {
                                 $id = $toId($value);
                                 if (!$id) {
                                     return 'Name must contain at least one letter or number.';
                                 }

                                 // if it isn't an add, we assume the caller will take care
                                 // of ensuring existence.
                                 if ($filter->getMode() !== $filter::MODE_ADD) {
                                     return true;
                                 }

                                 // try to get project (including deleted) matching the name
                                 $matchingProjects = ProjectModel::fetchAll(
                                     array(
                                         ProjectModel::FETCH_INCLUDE_DELETED => true,
                                         ProjectModel::FETCH_BY_IDS          => array($id)
                                     ),
                                     $p4
                                 );

                                 if ($matchingProjects->count() || in_array($id, $reserved)) {
                                     return 'This name is taken. Please pick a different name.';
                                 }

                                 return true;
                             }
                         )
                     )
                 )
            )
        );

        // ensure there is at least one team member
        $this->add(
            array(
                 'name'         => 'members',
                 'filters'      => array(
                     array(
                         'name'     => 'Callback',
                         'options'  => array(
                             'callback' => function ($value) {
                                 // throw away any provided keys
                                 return array_values((array) $value);
                             }
                         )
                     )
                 ),
                 'validators'    => array(
                     array(
                         'name'      => 'NotEmpty',
                         'options'   => array(
                             'message'   => 'Team must contain at least one member.'
                         )
                     ),
                     array(
                         'name'      => '\Application\Validator\IsArray'
                     ),
                     array(
                         'name'      => '\Application\Validator\Callback',
                         'options'   => array(
                             'callback'  => $usersValidatorCallback
                         )
                     )
                 )
            )
        );

        // add owners field
        $this->add(
            array(
                 'name'     => 'owners',
                 'required' => false,
                 'filters'  => array(
                     array(
                         'name'     => 'Callback',
                         'options'  => array(
                             'callback' => function ($value) {
                                 // treat empty string as null and throw away any provided keys
                                 return $value === '' ? null : array_values((array) $value);
                             }
                         )
                     )
                 ),
                 'validators'    => array(
                     array(
                         'name'      => '\Application\Validator\IsArray'
                     ),
                     array(
                         'name'      => '\Application\Validator\Callback',
                         'options'   => array(
                             'callback'  => $usersValidatorCallback
                         )
                     )
                 )
            )
        );

        // ensure description is a string
        $this->add(
            array(
                 'name'      => 'description',
                 'required'  => false,
                 'filters'   => array('trim')
            )
        );

        // ensure branches is an array
        $this->add(
            array(
                 'name'          => 'branches',
                 'required'      => false,
                 'filters'   => array(
                     array(
                         'name'  => 'Callback',
                         'options'   => array(
                             'callback'  => function ($value) use ($toId) {
                                 // treat empty string as null
                                 $value = $value === '' ? null : $value;

                                 // normalize the posted branch details to only contain our expected keys
                                 // also, generate an id (based on name) for entries lacking one
                                 $normalized = array();
                                 $defaults   = array(
                                    'id'            => null,
                                    'name'          => null,
                                    'paths'         => '',
                                    'moderators'    => array()
                                 );
                                 foreach ((array) $value as $branch) {
                                     $branch = (array) $branch + $defaults;
                                     $branch = array_intersect_key($branch, $defaults);

                                     if (!strlen($branch['id'])) {
                                         $branch['id'] = $toId->filter($branch['name']);
                                     }

                                     // turn our paths text input into an array
                                     // trim and remove any empty entries
                                     $branch['paths'] = array_filter(
                                         array_map(
                                             'trim',
                                             preg_split("/[\n\r]+/", $branch['paths'])
                                         ),
                                         'strlen'
                                     );

                                     $normalized[] = $branch;
                                 }

                                 return $normalized;
                             }
                         )
                     )
                 ),
                 'validators'    => array(
                     array(
                         'name'      => '\Application\Validator\Callback',
                         'options'   => array(
                             'callback'  => function ($value) use ($usersValidatorCallback, $p4, $translator) {
                                 // ensure all branches have a name and id.
                                 // also ensure that no id is used more than once.
                                 $ids        = array();
                                 $branchPath = new BranchPathValidator(array('connection' => $p4));
                                 foreach ((array) $value as $branch) {
                                     if (!strlen($branch['name'])) {
                                         return "All branches require a name.";
                                     }

                                     // given our normalization, we assume an empty id results from a bad name
                                     if (!strlen($branch['id'])) {
                                         return 'Branch name must contain at least one letter or number.';
                                     }

                                     if (in_array($branch['id'], $ids)) {
                                         return $translator->t("Two branches cannot have the same id.") . ' '
                                            . $translator->t("'%s' is already in use.", array($branch['id']));
                                     }

                                     // validate branch paths
                                     if (!$branchPath->isValid($branch['paths'])) {
                                         return $translator->t("Error in '%s' branch: ", array($branch['name']))
                                            . implode(' ', $branchPath->getMessages());
                                     }

                                     // verify branch moderators
                                     $moderatorsCheck = $usersValidatorCallback($branch['moderators']);
                                     if ($moderatorsCheck !== true) {
                                         return $moderatorsCheck;
                                     }

                                     $ids[] = $branch['id'];
                                 }

                                 return true;
                             }
                         )
                     )
                 )
            )
        );

        // ensure jobview is properly formatted
        // to start with we are only supporting one or more key=value pairs or blank.
        $this->add(
            array(
                 'name'         => 'jobview',
                 'required'     => false,
                 'filters'      => array('trim'),
                 'validators'   => array(
                     array(
                         'name'      => '\Application\Validator\Callback',
                         'options'   => array(
                             'callback'  => function ($value) {
                                 if (!strlen($value)) {
                                     return true;
                                 }

                                 $filters = preg_split('/\s+/', $value);
                                 foreach ($filters as $filter) {
                                     if (!preg_match('/^([^=()|]+)=([^=()|]+)$/', $filter)) {
                                         return "Job filter only supports key=value conditions and the '*' wildcard.";
                                     }
                                 }

                                 return true;
                             }
                         )
                     )
                 )
            )
        );

        // ensure emailFlags is an array containing keys for the flags we want to set
        $this->add(
            array(
                 'name'     => 'emailFlags',
                 'required' => false,
                 'filters'  => array(
                     array(
                         'name'     => 'Callback',
                         'options'  => array(
                             'callback' => function ($value) {
                                     return is_array($value) && !empty($value) ? $value : null;
                             }
                         )
                     )
                 )
            )
        );

        // ensure tests is an array with 'enabled' and 'url' keys
        $this->add(
            array(
                 'name'      => 'tests',
                 'required'  => false,
                 'validators'   => array(
                    array(
                        'name'      => '\Application\Validator\Callback',
                        'options'   => array(
                            'callback'  => function ($value) {
                                $params = isset($value['postParams']) ? trim($value['postParams']) : '';
                                $format = isset($value['postFormat']) ? trim(strtoupper($value['postFormat'])) : 'GET';

                                // no validation if there are no parameters
                                if (!strlen($params)) {
                                    return true;
                                }

                                // we only support JSON and GET formatted parameters
                                if ($format != 'GET' && $format != 'JSON') {
                                    return 'POST parameters for tests can only be in GET or JSON format.';
                                }

                                // validate based on format
                                if ($format == 'GET') {
                                    parse_str($params, $parsed);
                                    if (!count($parsed)) {
                                        return 'POST parameters expected to be in GET format, but were invalid.';
                                    }
                                } else {
                                    $params = @json_decode($params, true);
                                    if (is_null($params)) {
                                        return 'POST parameters expected to be in JSON format, but were invalid.';
                                    }
                                }

                                return true;
                            }
                        )
                    )
                 ),
                 'filters'   => array(
                     array(
                         'name'  => 'Callback',
                         'options'   => array(
                             'callback'  => function ($value) {
                                 $value = (array) $value + array(
                                         'enabled'    => false,
                                         'url'        => '',
                                         'postParams' => '',
                                         'postFormat' => 'GET'
                                     );

                                 return array(
                                     'enabled'     => (bool)   $value['enabled'],
                                     'url'         => (string) $value['url'],
                                     'postParams'  => trim((string) $value['postParams']),
                                     'postFormat'  => trim(strtoupper((string) $value['postFormat']))
                                 );
                             }
                         )
                     )
                 )
            )
        );

        // ensure deploy is an array with 'enabled' and 'url' keys
        $this->add(
            array(
                 'name'      => 'deploy',
                 'required'  => false,
                 'filters'   => array(
                     array(
                         'name'  => 'Callback',
                         'options'   => array(
                             'callback'  => function ($value) {
                                 $value = (array) $value + array('enabled' => false, 'url' => '');

                                 return array(
                                     'enabled' => (bool)   $value['enabled'],
                                     'url'     => (string) $value['url']
                                 );
                             }
                         )
                     )
                 )
            )
        );
    }

    /**
     * Mark given element as 'not allowed'. Validation of such element will always
     * fail. Given element will also be marked 'not required' to avoid failing if
     * value is not present.
     *
     * @param   string      $element    element to mark as not-allowed
     * @return  Project     provides fluent interface
     */
    public function setNotAllowed($element)
    {
        $input = isset($this->inputs[$element]) ? $this->inputs[$element] : null;
        if (!$input) {
            throw new \InvalidArgumentException(
                "Cannot set '$element' element NotAllowed - element not found."
            );
        }

        // tweak the element to:
        //  - make it not required (also sets allow empty)
        //  - don't allow empty values to overrule the opposite after making it not required
        //  - set our own validator chain containing only one validator always failing
        $validatorChain = new ValidatorChain;
        $validatorChain->attach(
            new CallbackValidator(
                function ($value) {
                    return 'Value is not allowed.';
                }
            )
        );
        $input->setRequired(false)
              ->setAllowEmpty(false)
              ->setValidatorChain($validatorChain);

        return $this;
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
