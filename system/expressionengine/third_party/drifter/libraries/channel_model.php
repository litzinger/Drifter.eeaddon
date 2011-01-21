<?php 

class Drifter_Channel_Model extends Channel_model 
{
    /**
     * Get Channel Fields
     *
     * Returns field information
     *
     * @access  public
     * @param   int
     * @return  mixed
     */
    function get_channel_fields($field_group, $fields = array())
    {
        if (count($fields) > 0)
        {
            $fields = ', '. implode(',', $fields);
        }
        else
        {
            $fields = '';
        }
        
        $channel_id = $this->input->get_post('channel_id');
        
        if(!$field_group)
        {
            $this->lang->loadfile('drifter');
            return $this->output->show_user_error('general', array(lang('no_field_group')));
        }
        
        $query = $this->db->query("SELECT *". $fields ." FROM ". $this->db->dbprefix ."channel_fields 
            WHERE group_id = ". $field_group ." OR (field_is_drifter = 'y' AND drifter_channels LIKE '%{". $channel_id ."}%') ORDER BY field_order");
        
        $query = $query->result_array();
        
        // Filter out the fields that are flagged as drifter, but not to the current channel
        foreach($query as $k => $row)
        {
            if($row['field_is_drifter'] == 'y' AND !in_array('{'. $channel_id .'}', explode(" ", $row['drifter_channels'])))
            {
                unset($query[$k]);
            }
        }
        
        // Do another query with the newly filtered query of field_ids. 
        // Everything that calls this method is expecting a result object, not an array to be returned, 
        // so lets keep them happy since we jacked up the query object above.
        $field_ids = array();
        foreach($query as $k => $row)
        {
            $field_ids[] = $row['field_id'];
        }
        
        $query = $this->db->query("SELECT * FROM ". $this->db->dbprefix ."channel_fields WHERE field_id IN (". implode(',', $field_ids) .")");
        
        return $query;
    }
}