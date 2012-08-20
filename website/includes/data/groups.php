<?php
/**
 * Class definition for groups
 * 
 * @author <gunther@keryx.se>
 * @version "Under construction 1"
 * @license http://www.mozilla.org/MPL/
 * @package webbteknik.nu
 * 
 */

/**
 * group
 *
 * Name and description of groups offered at the national level or by the institute
 * 
 * @todo interface and/or abstract class for all data types
 */
class data_groups extends items implements data
{
    
    // All properties that are not i abstract class are written as groupProp
    
    // Inherit
    // + id   <=> groupID in DB
    // + name <=> group_nickname in DB
    
    // "Foreign keys" must map to pre-existing record in DB
    // Sanitization rules are imported, validation used via DB

    /**
     * At what school does this course exist
     * 
     * Foreign key
     */
    protected $schoolID;

    /**
     * What course is it that the group studies
     * 
     * Foreign key
     */
    protected $courseID;

    /**
     * The web page of the group
     * 
     * May be null, if no URL exists
     */
    protected $groupUrl = null;

    /**
     * The maximum number of students in the group, not including teachers
     */
    protected $groupSize = 0;

    
    /**
     * Start date the group
     * 
     * Formatted YYYY-MM-DD
     */
    protected $groupDate = null;

    

    /**
     * Rules for filter_input_array/filter_var_array, sanitization step
     */
    protected static $filterSanitizeRules = array(
        'id' => array(
            'filter' => FILTER_SANITIZE_STRIPPED,
            'flags'  => FILTER_FLAG_STRIP_LOW
        ),
        'name' => array(
            'filter' => FILTER_SANITIZE_STRIPPED,
            'flags'  => 68
        ),
        'groupSize' => array(
            'filter' => FILTER_SANITIZE_NUMBER_INT
        ),
        'groupDate' => array(
            'filter' => FILTER_SANITIZE_STRIPPED,
            'flags'  => 68
        ),
        'groupUrl' => array(
            'filter' => FILTER_SANITIZE_URL
        )
    );
    // 68 == FILTER_FLAG_STRIP_LOW|FILTER_FLAG_ENCODE_AMP    

    /**
     * Rules for filter_input_array/filter_var_array, validation step
     * 
     * @todo Validate date using callback
     */
    protected static $filterValidateRules = array(
        'id' => array(
            'filter'  => FILTER_VALIDATE_REGEXP,
            'options' => array( 'regexp' => "/^[a-z0-9]{5}$/u" )
        ),
        'name' => array(
            'filter'  => FILTER_VALIDATE_REGEXP,
            'options' => array( 'regexp' => "/^\\p{L}[\\p{L}\\x20\\p{Pd}&#38;]{2,20}$/u" )
        ),
        'groupSize' => array(
            'filter'  => FILTER_VALIDATE_INT,
            'flags'   => FILTER_REQUIRE_SCALAR,
            'options' => array('min_range' => 1, 'max_range' => 500)
        ),
        'groupDate' => array(
            'filter'  => FILTER_VALIDATE_REGEXP,
            'options' => array( 'regexp' => "/^20[1-3][0-9]-[01][0-9]-[0-3][0-9]$/u" )
        ),
        'groupUrl' => array(
            'filter'  => FILTER_VALIDATE_URL,
            'flags'   => FILTER_FLAG_SCHEME_REQUIRED
        ),
        'schoolID' => array(
            'filter'  => FILTER_CALLBACK,
            'flags'   => "data_scools::isExistingId"
        ),
        'courseID' => array(
            'filter'  => FILTER_CALLBACK,
            'flags'   => "data_courses::isExistingId"
        ),
    );
    // The u-flag also ensures UTF-8 validity
    // Pd = Punctuation, Dash
    // L  = Letter
    // &#38; is allowed, for encoded &
    
    
    /**
     * Rules for filter_input_array/filter_var_array, validation step
     */
    protected $errorStrings = array(
        'id' => "Fel format, inte enligt /^[a-z0-9]{5}$/u",
        'name' => "Fel format, inte enligt /^\\p{L}[\\p{L}\\x20\\p{Pd}&#38;]{2,20}$/u",
        'groupUrl' => "Inte en URL. (Den måste inkludera schema.)"
    );
/*
        'id' => "För kort (min 5), för långt (max 6), eller otillåtna tecken",
        'name' => "För kort (min 2), för långt (max 100), eller otillåtna tecken",
        'groupPlace' => "För kort (min 2), för långt (max 50), eller otillåtna tecken",
        'groupUrl' => "Inte en URL. (Den måste inkludera schema.)"
*/

    private function __construct($id, $name, $groupPlace, $groupUrl)
    {
        $this->id          = $id;
        $this->name        = $name;
        $this->groupPlace = $groupPlace;
        $this->groupUrl   = $groupUrl;
    }
    
    /**
     * Loads an instance from DB
     * 
     * @param string $id  The group ID, matches DB primary key
     * @param object $dbh Instance of PDO
     */
    public static function loadOne($id, PDO $dbh) {
        $sql  = <<<SQL
            SELECT groupID AS id, group_name AS name, group_place AS groupPlace, group_url AS groupUrl
            FROM groups WHERE groupID = :id
SQL;
        $stmt = $dbh->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $stmt->setFetchMode(
            PDO::FETCH_CLASS|PDO::FETCH_PROPS_LATE, __CLASS__, array('id', 'name', 'groupPlace', 'groupUrl')
        );
        return $stmt->fetch();
    }
    
    /**
     * Return array of objects with all available records
     * 
     * @todo set limits, interval for pagination, etc
     * 
     * @param object $dbh Instance of PDO
     * @param string $dbh Custom SQL query
     * @return array of instances of this class
     */
    public static function loadAll(PDO $dbh, $sql = false) {
        if ( !$sql ) {
        $sql  = <<<SQL
            SELECT groupID AS id, group_name AS name, group_place AS groupPlace, group_url AS groupUrl
            FROM groups
            ORDER BY name
SQL;
        }
        $stmt = $dbh->prepare($sql);
        $stmt->execute();
        $stmt->setFetchMode(
            PDO::FETCH_CLASS|PDO::FETCH_PROPS_LATE, __CLASS__, array('id', 'name', 'groupPlace', 'groupUrl')
        );
        return $stmt->fetchAll();
    }

    /**
     * Make a new object from user data
     * 
     * @param Array $arr
     */
    public static function fromArray($arr)
    {
        if ( !isset($arr['id']) || empty($arr['name']) || empty($arr['groupPlace']) ) {
            echo "<pre>";
            trigger_error("Trying to create group object with too little data", E_USER_NOTICE);
            return false;
        }
        $groupUrl  = ( empty($arr['groupUrl']) ) ? '' : $arr['groupUrl'];
        $obj = new data_groups($arr['id'], $arr['name'], $arr['groupPlace'], $groupUrl);
        $obj->validate();
        if ( empty($arr['id']) ) {
            $obj->generateId();
            $obj->validate();
        }
        return $obj;
        
    }    
    
    /**
     * Generate an id from name and place
     */
    private function generateId()
    {
        // Debug with FirePHP;
        $fphp = $GLOBALS['FIREPHP'];
        
        // Name and place must be error free properties
        if ( $this->isError('name') || $this->isError('groupPlace') || !$this->propertyErrors['tested'] ) {
            trigger_error("Trying to create id for group from untested or faulty data.", E_USER_NOTICE);
            return false;
        }
        // Temporarily undo any escaping och ampersand
        $name  = str_replace("&#38;", "&", $this->name);
        $place = str_replace("&#38;", "&", $this->groupPlace);
        
        // Only allow [a-z], replace all else logically and convert all to lower case
        $name  = normalize_chars($name);
        $place = normalize_chars($place);
        
        // The words "skolan" and "gymnasiet" should be separate
        // E.g. "byskolan"" => "by|skolan"
        $name = str_replace("skolan", "|skolan", $name);
        $name = str_replace("gymnasiet", "|gymnasiet", $name);
        
        // Count number of words available to create id from
        // LOCALE should be swedish to (not) match åäö
        $n_words = preg_split("/\\W+/", $name);
        $p_words = preg_split("/\\W+/", $place);
        $nc = count($n_words) - 1; // Position in array of last word, hence - 1
        $pc = count($p_words) - 1;
        $tot_words = $nc + $pc + 2; // Total number of words
        switch ($tot_words) {
        case 2:
            $fphp->log("generating groupID from 2 words total.");
            $id = substr($name, 0, 2) . substr($name, -1, 1) . substr($place, 1, 1) . substr($place, -1, 1); 
            break;
        case 3:
            $fphp->log("generating groupID from 3 words total.");
            if ( 1 == $nc ) {
                // First letter in first name-word, first and last in 2nd name-word
                $id = substr($name, 0, 1) . substr($n_words[1], 0, 1) . substr($name, -1, 1) .
                      substr($place, 0, 1) . substr($place, -1, 1);
            } else {
                $id = substr($name, 0, 1) . substr($name, -1, 1) .
                      substr($place, 0, 1) . substr($p_words[1], -1, 1) .substr($place, -1, 1);
            }
            break;
        case 4:
            $fphp->log("generating groupID from 4 words total.");
            if ( 2 == $nc ) {
                // First letter in first, 2nd and last name-word
                $id = substr($name, 0, 1) . substr($n_words[1], 0, 1) . substr($n_words[2], 0, 1) .
                      substr($place, 0, 1) . substr($place, -1, 1);
                
            } elseif ( 1 == $nc ) {
                $id = substr($name, 0, 1) . substr($n_words[1], 0, 1) . substr($name, -1, 1) .
                      substr($place, 0, 1) . substr($p_words[1], 0, 1);
            } else {
                // A 3 word place, is there such a place???
                $id = substr($name, 0, 1) . substr($name, -1, 1) .
                      substr($place, 0, 1) . substr($p_words[1], 0, 1) . substr($p_words[2], 0, 1);
            }
            break;
        default:
            $fphp->log("generating groupID from 5 or more words total.");
            // 5 or more words
            // switch inside switch is hard to read, using if -else instead
            if ( 0 == $nc ) {
                // Always use 2 letters from name, use last place-word
                $id = substr($name, 0, 1) . substr($name, -1, 1) .
                      substr($place, 0, 1) . substr($p_words[1], 0, 1) . substr($p_words[$pc], 0, 1);
            } elseif ( 1 == $nc) {
                $id = substr($name, 0, 1) . substr($n_words[1], 0, 1) .
                      substr($place, 0, 1) . substr($p_words[1], 0, 1) . substr($p_words[$pc], 0, 1);
            } elseif ( 2 == $nc) {
                $id = substr($name, 0, 1) . substr($n_words[1], 0, 1) . substr($n_words[2], 0, 1) .
                      substr($place, 0, 1) . substr($p_words[$pc], 0, 1);
            } else {
                // $nc >= 3
                // Always use 2 letters from place
                $id = substr($name, 0, 1) . substr($n_words[1], 0, 1) .
                      substr($n_words[$nc], 0, 1) . substr($place, 0, 1);
                if ( $pc >= 1 ) {
                    $id .= substr($p_words[$pc], 0, 1);
                } else {
                    $id .= substr($place, -1, 1);
                }
            }
        }
        $fphp->log("The generated groupID pre-DB is: " . $id);
        // Construction of pattern complete. Check availability
        // Find highest already in use
        // Geberated data is SQL-injection safe
        $sql = <<<SQL
                SELECT groupID from groups
                WHERE groupID lIKE '{$id}%'
                ORDER BY groupID DESC
                LIMIT 0,1
SQL;
        $dbx      = config::get('dbx');
        $dbh      = keryxDB2_cx::get($dbx);
        $stmt     = $dbh->prepare($sql);
        $stmt->execute();
        $db_high_id = $stmt->fetchColumn();
        // If no other group has this letter-combination set this to 0 (zero)
        if ( empty($db_high_id) ) {
            $fphp->log("The generated groupID was first with the letter combination.");
            $id .= "0";
        } else {
            // Increment as character to get sequence from 0-9 and then a-z
            $last = substr($db_high_id, -1, 1);
            if ( is_numeric($last) ) {
                if ( $last == 9 ) {
                    $last = "a";
                }
            }
            $last++;
            if ( ord($last) > 123 ) {
                // We are past "z"
                trigger_error("Can not generate unique groupID.", E_USER_WARNING);
                return false;
            }
            $id .= $last;
        }
        $fphp->log("The final generated groupID is: " . $id);
        $this->id = $id;
        return true;
    } 
    
    /**
     * A mock/example object
     * 
     * Must not be savable
     * Same arguments as the constructor
     */
    public static function fake($id, $name, $groupPlace, $groupUrl="")
    {
        $fakeobj = new data_groups($id, $name, $groupPlace, $groupUrl);
        $fakeobj->isFake = true;
        return $fakeobj;
    }
    
    /**
     * Saving an object
     * 
     * Should only happen if it has been validated and is error free
     * @param object $dbh A PDO object
     * @return bool Successfully saved or not
     */
    public function save(PDO $dbh)
    {
        if ( $this->isFake() ) {
            trigger_error(E_USER_WARNING, "Trying to save a fake object");
            return false;
        }
        if ( $this->propertyErrors['tested'] == false ) {
            trigger_error(E_USER_NOTICE, "Can not save an object that is untested");
            return false;
        }
        if ( !$this->isErrorFree() ) {
            trigger_error(E_USER_WARNING, "Can not save an object that has errors");
            return false;
        }
        // TODO Add support for the num-fields
        $sql = <<<SQL
            INSERT INTO groups (groupID, group_name, group_place, group_url)
            VALUES (:groupID, :group_name, :group_place, :group_url)
SQL;
            /*
            ON DUPLICATE KEY UPDATE
            groupID = :groupID, group_name = :group_name, group_place = :group_place, group_url = :group_url
            */
        $stmt = $dbh->prepare($sql);
        $stmt->bindParam(':groupID', $this->id);
        $stmt->bindParam(':group_name', $this->name);
        $stmt->bindParam(':group_place', $this->groupPlace);
        $stmt->bindParam(':group_url', $this->groupUrl);
        return $stmt->execute();
        
    }
        
    /**
     * Get the place
     * 
     * @return string
     */
    public function getPlace()
    {
        return $this->groupPlace;
    }

    /**
     * Get the full name (includes place)
     * 
     * @return string
     */
    public function getFullName()
    {
        return "{$this->name}, {$this->groupPlace}";
    }

    /**
     * Get the url
     * 
     * @return string
     */
    public function getUrl()
    {
        return $this->groupUrl;
    }
    
    /**
     * Validate or extract groupid within parenthesis from end of string
     * 
     * Used to extract valid id when working with relationship-data
     * @param string $id Test string
     * @param bool   $extract Set to true to extract substring
     * @return mixed The valid/extracted id or false
     */
    public static function checkgroupId($id, $extract = false)
    {
    	$regexp = self::$filterValidateRules['id']['options']['regexp'];
        if ( !$extract ) {
            $test = preg_match($regexp, $id);
            return ( $test ) ? $id : false; 
        }
        // Modify regexp a bit
        // Remove "start token" (^)
        // Add literal parenthesis and capturing parenthesis
        $regexp = str_replace('^', '\((', $regexp);
        // Move "end token" ($) and repeat
        $regexp = str_replace('$', ')\)$', $regexp);
        $test   = preg_match($regexp, $id, $matches);
        return ( $test ) ? $matches[1] : false; 
    }
    
    
    public static function isExistingId($id, PDO $dbh)
    {
    	// TODO Validate single prop, before invoking DB
        $sql  = "SELECT count(*) FROM groups where groupID = :id";
        $stmt = $dbh->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetchColumn();
    }
    
}