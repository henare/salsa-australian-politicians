<?php
include_once(dirname(__FILE__) . "/salsa-core.php");
include_once(dirname(__FILE__) . "/salsa-supporter.php");

/**
 * Encapsulates an action in the Salsa framework.
 */
class SalsaAction extends SalsaObject {
    public $action_KEY;
    public $organization_KEY;
    public $chapter_KEY;
    public $Title;
    public $Description;
    public $Required;
    public $Request;
    public $email_trigger_KEYS;
    public $add_to_groups_KEYS;
    public $optionally_add_to_groups_KEYS;
    public $Thank_You_Text;
    public $redirect_path;
    public $Restriction_Type;
    public $Restricted_Districts;
    public $Restricted_Regions;
    public $Allow_Anonymous;
    public $Allow_Comments;
    public $Comment_Question;
    
    protected $fields;
    protected $targets;
    protected $contents;
    
    function __construct($xml) {
        parent::__construct($xml);
    }
    
    /**
     * Gets an action by its unique key.
     * 
     * @param integer $action_KEY The unique key of the action.
     * @return SalsaAction The action with the given key.
     */
    public static function get($action_KEY) {
        $conn = SalsaConnector::instance();
        if ($conn) {
            return $conn->getObject('action', $action_KEY, 'SalsaAction');
        }
        return NULL;
    }
    
    /**
     * Gets a list of all the available actions.
     * 
     * @return array<SalsaAction> A list of all the actions.
     */
    public static function getAll() {
        $conn = SalsaConnector::instance();
        if ($conn) {
            return $conn->getObjects('action', NULL, NULL, 'SalsaAction');
        }
        return array();
    }
    
    /**
     * Gets the list of targets associated with the action.  The list of targets
     * is retrieved from targetJSON.sjs and only supports the Actions3 framework. 
     * 
     * @param object The supporter fields submitted by the user
     * 
     * @return array<SalsaTarget> A list of target or recipient objects.
     */
    public function getTargets($supporter) {
        if (!empty($this->targets)) {
            return $this->targets;
        }
        $this->targets = array();
        $conn = SalsaConnector::instance();
        if (!$conn) {
            return $this->targets;
        }
        
        // Convert to a supporter object
        if (is_array($supporer)) {
            $supporter = new SalsaSupporter($supporter);
        }
        
        // Get all the targets (the Actions3 way)
        $params = $supporter->getLocation();
        $params['action_KEY'] = $this->action_KEY;
        
        $res = $conn->postJson("/o/$this->organization_KEY/p/dia/action3/common/public/targetJSON.sjs", $params);
        
        // Cast the targets to SalsaTargets.
        if (!empty($res)) {
            foreach ($res as $t) {
                $target = new SalsaTarget($t);
                $this->targets[$target->key] = $target;
            }
        }
        if (!empty($this->targets)) {
            return $this->targets;
        }
        return $this->targets;
    }
    
    /**
     * Returns true if the action has any targets configured.  For example,
     * a petition never has any targets.
     * 
     * @return boolean True if targets have been configured, false otherwise.
     */
    public function hasTargets() {
        $conn = SalsaConnector::instance();
        if (!$conn) {
            return false;
        }
        $count = $conn->getCount('action_target', "action_KEY=$this->action_KEY");
        return ($count > 0);
    }
    
    /**
     * Returns all the available content for this action.
     * 
     * @return array<SalsaContent> The available content.
     */
    public function getContents() {
        if (!empty($this->contents)) {
            return $this->contents;
        }
        $this->contents = array();
        $conn = SalsaConnector::instance();
        if (!$conn) {
            return $this->contents;
        }
        
        // Get each content set for this action
        $xml = $conn->getObjects('action_content', "action_KEY=$this->key");
        if (!empty($xml)) {
            foreach ($xml->action_content->item as $item) {
                $action_content_KEY = (string)$item->action_content_KEY;
                
                // Get the message for this content set
                $content = SalsaContent::get($action_content_KEY);
                if (!empty($content)) {
                    $this->contents[$action_content_KEY] = $content;
                }
            }
        }
        return $this->contents;
    }
    
    /**
     * Gets the definitions of the user fields.
     * 
     * @return array<object> An array of fields 
     */
    public function getSupporterFields() {
        if (empty($this->fields)) {
            $this->fields = array();
            $this->fields['description'] = new SalsaSupporterFieldLabel('description', 'Description', $this->Description);

            // Get the supporter fields
            $request = split(',', $this->Request);
            $required = split(',', $this->Required);
            $required = array_flip($required);
            
            foreach ($request as $id) {
                $this->fields[$id] = SalsaSupporterField::get($id, isset($required[$id]));
            }
            $this->fields['footer'] = new SalsaSupporterFieldLabel('footer', 'Footer', $this->Footer);
            
            // Get the groups
            $groups = $this->getGroups();
            if (!empty($groups)) {
                $this->fields['groups'] = new SalsaSupporterFieldGroupBoxes($groups);
            }
            
            // Get the content
            $contents = $this->getContents();
            foreach ($contents as $content_KEY => $content) {
                $id = "content_{$content_KEY}";
                $this->fields[$id] = new SalsaSupporterFieldLabel($id, 'Content', $content->Recommended_Content);
                $this->fields[$id]->classes .= ' nsjalapeno--content';
            }
            
            // If anonymous comments are allowed, show the checkbox
            if ($this->Allow_Anonymous == 'true') {
            	$this->fields['Anonymous'] = new SalsaSupporterFieldCheckBox('Anonymous', 'Display in list as anonymous');
            }
            // If comments are allowed, show the comment box
            if ($this->Allow_Comments == 'true') {
            	$this->fields['Comment'] = new SalsaSupporterFieldTextArea('Comment', false, $this->Comment_Question);
            }
        }
        return $this->fields;
    }
    
    public function getGroups() {
        // Get the names of all the groups
        $conn = SalsaConnector::instance();
        if (!$conn) {
            return array();
        }
        $group_names = array();
        
        $xml = $conn->getObjects('groups', null, array('includes' => 'Group_Name'));
        if ($xml) {
            foreach ($xml->groups->item as $group) {
                $group_names[(string)$group->key] = (string)$group->Group_Name;
            }
        }
        
        $out = array();
        $group_KEYS = explode(',', $this->optionally_add_to_groups_KEYS);
        if (!empty($group_KEYS)) {
            foreach ($group_KEYS as $group_KEY) {
                if (!empty($group_KEY) && !empty($group_names[$group_KEY])) {
                    $out[$group_KEY] = $group_names[$group_KEY];
                }
            }
        }
        return $out;
    }
    
    /**
     * @param SalsaSupporter $supporter
     */
    public function isRestricted($supporter) {
    	$location = $supporter->getLocation();
		
		$restricted_region = false;
		$restricted_district = false;
		
		if ($this->Restriction_Type == 'Restricted') {
			$regions = $this->Restricted_Regions;
			$districts = $this->Restricted_Districts;
			
			// Is the region allowed?
			if (!empty($location['region']) && !empty($regions)) {
				if (strpos($regions, $location['region']) === false) {
					$restricted_region = true;
				}
			}
			
			// Is the district allowed?
			if (!empty($location['districts']) && !empty($districts)) {
				$restricted_district = true;
				
				foreach ($location['districts'] as $district) {
					if (strpos($districts, $district) !== false) {
						$restricted_district = false;
					}
				}
			}
		}
		if ($restricted_region || $restricted_district) {
			return $this->Restricted_Text;
		}
		return false;
    }
    
    /**
     * Call this method to submit the form to Salsa.  The supporter fields
     * should already be validated by this point.  Which fields were actually
     * displayed in the form does not matter at this point; we will only submit
     * values that match one of the available supporter fields.
     * 
     * @param array The supporter form values submitted by the user
     * @return string The resulting HTML page.
     */
    public function submit($supporter) {
        // Get the connect and make sure it succeeded.
        $conn = SalsaConnector::instance();
        if (!$conn) {
            return 'ERROR: The server was not initialized.';
        }
        if (!empty($conn->errors)) {
            return NULL;
        }
            
        // Load the targets and contents for the given supporter
        if (is_array($supporter)) {
            $supporter = new SalsaSupporter($supporter);
        }
        // If the action is restricted, the user is unable to complete this action.
        $restricted = $this->isRestricted($supporter);
        if (!empty($restricted)) {
        	$conn->addErrors($restricted);
        	return NULL;
        }
        
        // Get the fields, targets and content
        $fields = $this->getSupporterFields();
        $targets = $this->getTargets($supporter);
        $contents = $this->getContents();
        
        // If we found no matching targets, the user is unable to complete
        // this action.
        if (empty($targets) && $this->hasTargets()) {
            $conn->addErrors(<<<EOT
Sorry, we were unable to match your location to any recipients.  
Either the address you gave was not recognized, or this action is only 
available to supporters in a different location.
EOT
);
            return NULL;
        }

        // Start filling out the post parameters
        $p = array();
        $p['action_KEY'] = $this->key;
        $p['organization_KEY'] = $this->organization_KEY;
        $p['chapter_KEY'] = $this->chapter_KEY;
        $p['email_trigger_KEYS'] = $this->email_trigger_KEYS;

        // Contents
        $content_KEYS = '0';
        foreach ($contents as $key => $content) {
            $subjectKey = "Subject$key";
            $messageKey = "Content$key";
            $p[$subjectKey] = $content->Recommended_Subject;
            $p[$messageKey] = $content->Recommended_Content;
            
            // If the user submitted different content, use it
            if (!empty($supporter->$subjectKey)) {
                $p[$subjectKey] = $supporter->$subjectKey;
            }
            if (!empty($supporter->$messageKey)) {
                $p[$messageKey] = $supporter->$messageKey;
            }
            $content_KEYS .= ',' . $content->action_content_KEY;
        }
        $p['global:action_content_KEYS'] = $content_KEYS;

        // Targets
        foreach ($targets as $target) {
            $p['target_type'][] = $target->object;
            $p['target_key'][] = $target->key;
            $p['target_method'][] = $target->method;
            $p['target_subjectName'][] = "Subject$target->action_content_KEY";
            $p['target_contentName'][] = "Content$target->action_content_KEY";
        }

        // Supporter
        $p['table'] = 'supporter';
        $p['key'] = '0';
        $p['required'] = $this->Required;

        // Merge in the submitted supporter fields
        foreach($fields as $key => $field) {
            if (!empty($supporter->$key)) {
                $p[$key] = $supporter->$key;
            }
        }

        // Add the supporter to groups
        $group_KEYS = explode(',', $this->add_to_groups_KEYS);
        foreach ($group_KEYS as $group_KEY) {
            if (!empty($group_KEY)) {
                $p['link'][] = 'groups';
                $p['linkKey'][] = $group_KEY;
            }
        }
        $group_KEYS = explode(',', $this->optionally_add_to_groups_KEYS);
        foreach ($group_KEYS as $group_KEY) {
            if (!empty($group_KEY)) {
                $key1 = "groups_KEY{$group_KEY}";
                $key2 = "groups_KEY{$group_KEY}_checkbox";
                if (!empty($supporter->$key1)) {
                    $p[$key1] = $supporter->$key1;
                    $p[$key2] = $supporter->$key2;
                }
            }
        }
        
        $p['link'][] = 'action';
        $p['linkKey'][] = $this->key;

        $result = $conn->submitForm("/salsa/api/action/processAction2.jsp", $p);
        if ($result) {
            return $result;
        }
        
        $conn->addErrors('Could not submit the form');
        return NULL;
    }
    
    /**
     * Gets the supporters who took action most recently.
     * 
     * @param integer $action_KEY
     * @param integer $max The maximum number of results to show (defaults to 10)
     */
    public static function getSigners($action_KEY, $max = 10) {
        $out = array();
        if (empty($max)) {
        	$max = 10;
        }
        
        // Get the connect and make sure it succeeded.
        $conn = SalsaConnector::instance();
        if (!$conn || !empty($conn->errors)) {
            return $out;
        }
        
        // Get the supporters who took action
        $xml = $conn->getObjects('supporter_action', array(
          "action_KEY=$action_KEY",
        ), array(
          'orderBy' => 'Last_Modified DESC',
        ));
        $count = 0;
        foreach ($xml->supporter_action->item as $item) {
        	
        	// Only show supporters who have chosen not be anonymous
        	$anon = (int)$item->Anonymous;
        	if (empty($anon)) {
        		
	        	// Lookup the supporter
	        	$supporter_KEY = (string)$item->supporter_KEY;
	        	$out[] = SalsaSupporter::get($supporter_KEY);
	        	$count++;
        	}
        	if ($count >= $max) {
        		break;
        	}
        }
        return $out;
    }
}

class SalsaTarget extends SalsaObject {
    public $action_content_KEY;
    public $method;
    protected $display_name;
    
    function __construct($obj) {
        parent::__construct($obj);
    }
    
    /**
     * Gets the content associated with this target.
     * 
     * @param $action If specified, the content will be retrieved from the 
     *   given action object.  This is preferable, because if one of several
     *   message variants is randomly selected, this will ensure it is the
     *   same variant that appears elsewhere. 
     *   
     * @return SalsaContent The content associated with this target.
     */
    public function getContent($action = NULL) {
        if (!empty($action)) {
            $contents = $action->getContents();
            return $contents[$this->action_content_KEY];
        }
        return SalsaContent::get($this->action_content_KEY);
    }
    
    /**
     * Gets the full name of this target to display.
     */
    public function getDisplayName() {
        if ($this->display_name) {
            return $this->display_name;
        }
        $name = array();
        if (!empty($this->honorific)) {
            $name[] = $this->honorific;
        }
        if (!empty($this->given_name)) {
            $name[] = $this->given_name;
        }
        if (!empty($this->middle_name)) {
            $name[] = $this->middle_name;
        }
        if (!empty($this->family_name)) {
            $name[] = $this->family_name;
        }
        if (!empty($this->suffix)) {
            $name[] = $this->suffix;
        }
        $this->display_name = implode(' ', $name);
        return $this->display_name;
    }
}


class SalsaContent extends SalsaObject {
    public $action_KEY;
    public $action_content_KEY;
    public $action_content_detail_KEY;
    public $Name;
    public $Content_Type;
    public $Recommended_Subject;
    public $Recommended_Content;
    public $Fixed_Subject;
    public $Fixed_Content;
    
    function __construct($xml) {
        parent::__construct($xml);
        
        // Lookup the corresponding action_content object
        $conn = SalsaConnector::instance();
        if ($conn) {
            $xml = $conn->getObject('action_content', $this->action_content_KEY);
            if (!empty($xml)) {
                $item = $xml->action_content->item;
                $this->action_KEY = (string)$item->action_KEY;
                $this->Name = (string)$item->Name;
                $this->Content_Type = (string)$item->Content_Type;
            }
        }
    }
    
    /**
     * Gets one content message for the given content set.  If there are alternate
     * messages, one will be selected randomly. 
     * 
     * @param integer $action_content_KEY The identifier of the content set.
     * @return SalsaContent A single content object.
     */
    public static function get($action_content_KEY) {
        $conn = SalsaConnector::instance();
        if ($conn) {
            // Get the alternate messages for this target
            $contents = $conn->getObjects('action_content_detail', "action_content_KEY=$action_content_KEY", NULL, 'SalsaContent');
            
            // If there are alternate messages, choose one randomly.
            $key = 0;
            if (count($contents) > 1) {
                $key = array_rand($contents, 1);
            }
            
            if (!empty($contents)) {
                return $contents[$key];
            }
        }
        return NULL;
    }
}

class SalsaSupporter extends SalsaObject {
    
    public function __construct($obj) {
        parent::__construct($obj);
    }
    
    public static function get($supporter_KEY) {
        $conn = SalsaConnector::instance();
        if ($conn) {
            return $conn->getObject('supporter', $supporter_KEY, 'SalsaSupporter');
        }
        return NULL;
    }
    
    /**
     * Gets the full name of this target to display.
     */
    public function getDisplayName() {
        if ($this->display_name) {
            return $this->display_name;
        }
        $name = array();
        if (!empty($this->Title)) {
            $name[] = $this->Title;
        }
        if (!empty($this->First_Name)) {
            $name[] = $this->First_Name;
        }
        if (!empty($this->MI)) {
            $name[] = $this->MI;
        }
        if (!empty($this->Last_Name)) {
            $name[] = $this->Last_Name;
        }
        if (!empty($this->Suffix)) {
            $name[] = $this->Suffix;
        }
        $this->display_name = implode(' ', $name);
        return $this->display_name;
    }
    
    /**
     * Lookup as much information as we can about the supporter's location
     * by passing their given address fields into the warehouse.
     * 
     * @return array An array of parameters we can pass into targetJSON.sjs
     */
    public function getLocation() {
        $p = array();
        if (!empty($this->Country)) {
        	$p['country'] = $this->Country;
        }
        else {
            $p['country'] = 'us';
        }
        if (!empty($this->State)) {
            $p['region'] = $this->State;
        }
        if (!empty($this->Zip)) {
            $p['postal_code'] = $this->Zip;
        }
        if (!empty($this->PRIVATE_Zip_Plus_4)) {
            $p['postal_code_extension'] = $this->PRIVATE_Zip_Plus_4;
        }
        if (!empty($this->Street)) {
            $p['address1'] = $this->Street;
        }
        
        // Lookup the supporter's location
        $conn = SalsaConnector::instance();
        if ($conn) {
            $q = $conn->postJson('http://warehouse.democracyinaction.org/salsa/api/warehouse/append.jsp', 
              array_merge(array('json' => 'true'), $p));
        }
        if (!empty($q)) {
            $p = (array)$q;
        }
        return $p;
    }
}
