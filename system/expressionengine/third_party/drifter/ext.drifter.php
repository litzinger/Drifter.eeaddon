<?php

if (! defined('DRIFTER_VERSION'))
{
    // get the version from config.php
    require PATH_THIRD.'drifter/config.php';
    define('DRIFTER_VERSION', $config['version']);
    define('DRIFTER_NAME', $config['name']);
    define('DRIFTER_DESCRIPTION', $config['description']);
    define('DRIFTER_DOCS', $config['docs_url']);
}

/**
 * Drifter
 *
 * This extension enables Custom Fields to only display on specified channels.
 *
 * The concept for this extension comes from Matt Weinberg (EE Forums user slapshotw).
 * This is a port of the original Gypsy for EE 1.6 by Brandon Kelly to work in EE 2.0
 *
 * @package   Drifter
 * @author    
 * @link      
 * @copyright 
 * @license   http://creativecommons.org/licenses/by-sa/3.0/   Attribution-Share Alike 3.0 Unported
 */
class Drifter_ext
{
    /**
     * Extension Settings
     *
     * @var array
     */
    var $settings = array();
    var $name = DRIFTER_NAME;
    var $version = DRIFTER_VERSION;
    var $description = DRIFTER_DESCRIPTION;
    var $settings_exist = 'n';
    var $docs_url = DRIFTER_DOCS;
    var $cache;


    /**
     * Extension Constructor
     *
     * @param array   $settings
     * @since version 1.0.0
     */
    function Drifter_ext($settings=array())
    {
        $this->EE =& get_instance();
        $this->settings = $this->_get_settings();
        
        // Create cache
        if (! isset($this->EE->session->cache['drifter']))
        {
            $this->EE->session->cache['drifter'] = array();
        }
        $this->cache =& $this->EE->session->cache['drifter'];
    }

    

    /**
     * Activate Extension
     *
     * Resets all Drifter exp_extensions rows
     *
     * @since version 1.0.0
     */
    function activate_extension()
    {    
        // Delete old hooks
        $this->EE->db->query("DELETE FROM exp_extensions WHERE class = '". __CLASS__ ."'");
        
        // Add new extensions
        $ext_template = array(
            'class'    => __CLASS__,
            'settings' => '',
            'priority' => 10,
            'version'  => $this->version,
            'enabled'  => 'y'
        );
        
        $extensions = array(
            // Admin > Field Groups
            array('hook'=>'sessions_end', 'method'=>'sessions_end'),
            array('hook'=>'publish_form_channel_preferences', 'method'=>'publish_form_channel_preferences')
        );
        
        foreach($extensions as $extension)
        {
            $ext = array_merge($ext_template, $extension);
            $this->EE->db->insert('exp_extensions', $ext);
        }
        
        
        // Add field_is_drifter to exp_weblog_fields
        $query = $this->EE->db->query("SHOW COLUMNS FROM `exp_channel_fields` LIKE 'field_is_drifter'");
        if ($query->num_rows() == 0)
        {
            $this->EE->db->query("ALTER TABLE exp_channel_fields ADD COLUMN field_is_drifter CHAR(1) NOT NULL DEFAULT 'n'");
        }
        
        // Add drifter_weblogs to exp_weblog_fields
        $query = $this->EE->db->query("SHOW COLUMNS FROM `exp_channel_fields` LIKE 'drifter_channels'");
        if ($query->num_rows() == 0)
        {
            $this->EE->db->query("ALTER TABLE exp_channel_fields ADD COLUMN drifter_channels text NOT NULL DEFAULT ''");
        }
    }



    /**
     * Update Extension
     *
     * @param string   $current   Previous installed version of the extension
     * @since version 1.0.0
     */
    function update_extension($current='')
    { 
        if ($current == '' OR $current == $this->version)
        {
            return FALSE;
        }

        if($current < '1.1.3')
        {
            $this->EE->db->select('field_id, drifter_channels');
            $query = $this->EE->db->get('channel_fields');

            // Update existing hooks
            $this->EE->db->where('class', __CLASS__);
            $this->EE->db->update('exp_extensions', array('version' => $this->version));
            
            foreach($query->result_array() as $row)
            {
                // If a { is present, the update has already been run. Checking b/c this
                // appears to get called twice on the ext page load. why?
                if(!strstr($row['drifter_channels'], '{'))
                {
                    $ids = explode(" ", $row['drifter_channels']);
                    $new_ids = array();
                
                    foreach($ids as $id)
                    {
                        if($id != '')
                        {
                            $new_ids[] = '{'. $id .'}';
                        }
                    }

                    if(count($new_ids) > 0)
                    {
                        $new_ids = implode(" ", $new_ids);
                        $this->EE->db->where('field_id', $row['field_id']);
                        $this->EE->db->update('channel_fields', array('drifter_channels' => $new_ids));
                    }
                }
            }
        }
    }



    /**
     * Disable Extension
     *
     * @since version 1.0.0
     */
    function disable_extension() 
    {
        // Delete records
        $this->EE->db->where('class', __CLASS__);
        $this->EE->db->delete('exp_extensions');
        
        // Remove columns when un-installed
        $this->EE->db->query("ALTER TABLE exp_channel_fields DROP COLUMN field_is_drifter");
        $this->EE->db->query("ALTER TABLE exp_channel_fields DROP COLUMN drifter_channels");
    }
    
    
    function publish_form_channel_preferences($data)
    {
        if ($this->EE->extensions->last_call)
        {
            $data = $this->EE->extensions->last_call;
        }

        // Time to get creative...
        require 'libraries/channel_model.php';
        $this->EE->channel_model = new Drifter_Channel_Model;
        
        require 'libraries/Api_channel_entries.php';
        $this->EE->api_channel_entries = new Drifter_Api_Channel_Entries;
        
        return $data;
    }

    /**
     * Sessions End
     *
     * @see   http://expressionengine.com/developers/extension_hooks/sessions_end/
     * @since version 1.0.0
     */
    function sessions_end()
    {
        // Update the accessory so it doesn't appear where we don't need it to. Even though
        // jQuery hides the tab, lets reduce the risk of seeing flashing of the tab on pages where we don't need it.
        // Only run this query on the homepage too
        if($this->EE->input->get('D') == 'cp' AND $this->EE->input->get('C') == 'homepage')
        {
            $this->EE->db->where('class', 'Drifter_acc');
            $this->EE->db->update('accessories', array('controllers' => 'admin_content'));
        }
        
        if($this->EE->input->post('field_is_drifter') AND $this->EE->input->post('field_id'))
        {
            $field_id = $_POST['field_id'];
            $field_is_drifter = $_POST['field_is_drifter'];
            
            if($field_is_drifter == 'y') 
            {
                $ids = array();
                foreach($_POST['drifter_channels'] as $id)
                {
                    $ids[] = '{'. $id .'}';
                }
                $drifter_channels = implode(' ', $ids);
            } 
            else 
            {
                $drifter_channels = '';
            }
            
            $this->EE->db->where('field_id', $field_id);
            $this->EE->db->update('channel_fields', array('field_is_drifter' => $field_is_drifter, 'drifter_channels' => $drifter_channels));
        }
    }
    
    
    /**
    * Get the site specific settings from the extensions table
    * Originally written by Leevi Graham? Modified for EE2.0
    *
    * @param $force_refresh     bool    Get the settings from the DB even if they are in the session
    * @return array                     If settings are found otherwise false. Site settings are returned by default.
    */
    private function _get_settings($force_refresh = FALSE)
    {
        // assume there are no settings
        $settings = FALSE;
        $this->EE->load->helper('string');

        // Get the settings for the extension
        if(isset($this->cache['settings']) === FALSE || $force_refresh === TRUE)
        {
            // check the db for extension settings
            $this->EE->db->select('settings');
            $this->EE->db->where('enabled', 'y');
            $this->EE->db->where('class', __CLASS__);
            $this->EE->db->limit('1');
            $query = $this->EE->db->get('exp_extensions');

            // if there is a row and the row has settings
            if ($query->num_rows() > 0 && $query->row('settings') != '')
            {
                // save them to the cache
                $this->cache['settings'] = strip_slashes(unserialize($query->row('settings')));
            }
        }

        // check to see if the session has been set
        // if it has return the session
        // if not return false
        if(empty($this->cache['settings']) !== TRUE)
        {
            $settings = $this->cache['settings'];
        }

        return $settings;
    }
    
    private function debug($str)
    {
        echo '<pre>';
        var_dump($str);
        echo '</pre>';
    }
}