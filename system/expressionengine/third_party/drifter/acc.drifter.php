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
class Drifter_acc {

    var $name       = 'Drifter Accessory';
    var $id         = 'drifter';
    var $version        = DRIFTER_VERSION;
    var $description    = DRIFTER_DESCRIPTION;
    var $sections       = array();

    /**
     * Constructor
     */
    function Drifter_acc()
    {}
    
    function set_sections()
    {
        $this->EE =& get_instance();
        $this->EE->lang->loadfile('drifter');
        
        $script = '';
        $drifter_row = '<tr><td><strong>'. lang('drifter_is_drifter') .'</strong></td><td>';
        $channel_options = array();
        
        $script .= '
            $("#drifter.accessory").remove();
            $("#accessoryTabs").find("a.drifter").parent("li").remove();
        ';
        
        if(isset($this->EE->cp) AND $this->EE->input->get('D') == 'cp' AND $this->EE->input->get('M') == 'field_edit')
        {
            // Get the channels                              
            $this->EE->db->select('channel_id, channel_title');
            $this->EE->db->where('site_id', $this->EE->config->item('site_id'));
            $channels = $this->EE->db->get('exp_channels');
            
            foreach($channels->result_array() as $row) {
                $channel_options[$row['channel_id']] = htmlspecialchars($row['channel_title'], ENT_QUOTES);
            }
            
            $field_id = $this->EE->input->get_post('field_id');
            
            // We are editing an existing field
            if($field_id)
            {
                // Get the field data
                $this->EE->db->where('field_id', $this->EE->input->get_post('field_id'));
                $field_result = $this->EE->db->get('channel_fields');
            
                foreach($field_result->result_array() as $row)
                {
                    $field_is_drifter = $row['field_is_drifter'] == 'y' ? TRUE : FALSE;
                    $drifter_channels = str_replace(array('{', '}'), array('', ''), $row['drifter_channels']);
                    $drifter_channels = explode(" ", $drifter_channels);
                }
                
                $data = array(
                    'name'        => 'field_is_drifter',
                    'id'          => 'field_is_drifter_y',
                    'value'       => 'y',
                    'checked'     => $field_is_drifter === TRUE ? TRUE : FALSE,
                    'class'       => 'drifter_channels_toggle' 
                );

                $drifter_row .= form_radio($data) . ' <label for="field_is_drifter_y" class="drifter_channels_toggle">Yes</label>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';

                $data = array(
                    'name'        => 'field_is_drifter',
                    'id'          => 'field_is_drifter_n',
                    'value'       => 'n',
                    'checked'     => $field_is_drifter === FALSE ? TRUE : FALSE,
                    'class'       => 'drifter_channels_toggle'
                );

                $display = $field_is_drifter === TRUE ? 'block' : 'none';

                $drifter_row .= form_radio($data) . ' <label for="field_is_drifter_n" class="drifter_channels_toggle">No</label>';
                $drifter_row .= '<div style="margin-top: 1em">'. form_multiselect('drifter_channels[]', $channel_options, $drifter_channels, 'id="drifter_channels" size="10" style="display: '. $display .'"') .'</div>';

                $script .= '
                    $(".mainTable:eq(0)").append(\''. $drifter_row .'</td></tr>\');
                    $(".drifter_channels_toggle").click(function(){
                        if( $("#field_is_drifter_y").attr("checked") == true ){
                            $("#drifter_channels").show();
                        } else {
                            $("#drifter_channels").hide();
                        }
                    });
                ';
            }
            else // We are creating a new field
            {
                $drifter_row .= '<div>'. lang('drifter_new_field') .'</div>';
                $script .= '$(".mainTable:eq(0)").append(\''. $drifter_row .'</td></tr>\');';
            }
         
        }
        
        $this->EE->javascript->output('$(function(){'. preg_replace("/\s+/", " ", $script) .'});');
        $this->EE->javascript->compile();
    }
}
// END CLASS