<?php

require APPPATH .'libraries/api/Api_channel_entries.php';

class Drifter_Api_Channel_Entries extends Api_channel_entries
{
    /**
     * Prep data
     *
     * Prep all data we need to create an entry
     *
     * @access  private
     * @param   mixed
     * @param   mixed
     * @return  void
     */
    function _prepare_data(&$data, &$mod_data)
    {
        $this->instantiate('channel_categories');
    
        // Category parents - we toss the rest
    
        if (isset($data['category']) AND is_array($data['category']))
        {
            foreach ($data['category'] as $cat_id)
            {
                $this->EE->api_channel_categories->cat_parents[] = $cat_id;
            }

            if ($this->EE->api_channel_categories->assign_cat_parent == TRUE)
            {
                $this->EE->api_channel_categories->fetch_category_parents($data['category']);
            }
        }
        unset($data['category']);

        // Prep y / n values        
    
        $data['allow_comments'] = (isset($data['allow_comments']) && $data['allow_comments'] == 'y') ? 'y' : 'n';

        if (isset($data['cp_call']) && $data['cp_call'] == TRUE)
        {
            $data['allow_comments'] = ($data['allow_comments'] !== 'y' OR $this->c_prefs['comment_system_enabled'] == 'n') ? 'n' : 'y';
        }
    
        if ($this->c_prefs['enable_versioning'] == 'n')
        {
            $data['versioning_enabled'] = 'y';
        }
        else
        {
            if (isset($data['versioning_enabled']))
            {
                $data['versioning_enabled'] = 'y';
            }
            else
            {
                $data['versioning_enabled'] = 'n';
            
                // In 1.6, this happened right before inserting new revisions,
                // but it makes more sense here.
                $this->c_prefs['enable_versioning'] = 'n';
            }
        }
    
    
        // If we have the "honor_entry_dst" pref turned on we need to reverse the effects.
        $this->_cache['dst_enabled'] = 'n';

        if ($this->EE->config->item('honor_entry_dst') == 'y')
        {
            $this->_cache['dst_enabled'] = (isset($data['dst_enabled']) && $data['dst_enabled'] == 'y') ? 'y' : 'n';
        }
    
        $this->instantiate('channel_fields');

        /* Extending the core API only to change this query... ugh.
        $this->EE->db->select('field_id, field_name, field_label, field_type, field_required');
        $this->EE->db->join('channels', 'channels.field_group = channel_fields.group_id', 'left');
        $this->EE->db->where('channel_id', $this->channel_id);
        $query = $this->EE->db->get('channel_fields');
        */
        
        $sql = "SELECT cf.field_id, cf.field_name, cf.field_label, cf.field_type, cf.field_required 
                FROM exp_channels c
                LEFT JOIN exp_channel_fields cf ON (c.field_group = cf.group_id OR (cf.field_is_drifter = 'y' AND cf.drifter_channels LIKE '%{". $this->channel_id . "}%'))
                WHERE c.channel_id = $this->channel_id";

        $query = $this->EE->db->query($sql);
        
        if ($query->num_rows() > 0)
        {
            foreach ($query->result_array() as $row)
            {
                $field_name = 'field_id_'.$row['field_id'];
                
                $this->EE->api_channel_fields->settings[$row['field_id']]['field_id'] = $row['field_id'];
            
                if (isset($data[$field_name]) OR isset($mod_data[$field_name]))
                {
                    $this->EE->api_channel_fields->setup_handler($row['field_id']);

                    // Break out module fields here
                    if (isset($data[$field_name]))
                    {
                        $data[$field_name] = $this->EE->api_channel_fields->apply('save', array($data[$field_name]));
                    }
                    elseif (isset($mod_data[$field_name]))
                    {
                        $mod_data[$field_name] = $this->EE->api_channel_fields->apply('save', array($mod_data[$field_name]));
                    }
                }           
            }
        }
    }
    
    
    /**
     * Sync Related
     *
     * Inserts/updates related data for an entry
     *
     * @access  private
     * @param   mixed
     * @param   mixed
     * @return  void
     */
    function _sync_related($meta, &$data)
    {
        // Insert Categories
        
        $this->instantiate('channel_categories');
        
        if ($this->EE->api_channel_categories->cat_parents > 0)
        {
            $this->EE->api_channel_categories->cat_parents = array_unique($this->EE->api_channel_categories->cat_parents);

            sort($this->EE->api_channel_categories->cat_parents);

            foreach($this->EE->api_channel_categories->cat_parents as $val)
            {
                if ($val != '')
                {
                    $this->EE->db->insert('category_posts', array('entry_id' => $this->entry_id, 'cat_id' => $val));
                }
            }
        }


        // Recompile Relationships

        $this->update_related_cache($this->entry_id);
        
        
        // Is a page being updated or created?
        
        if ($this->EE->config->item('site_pages') !== FALSE && $this->_cache['pages_enabled'] && $this->EE->input->post('pages_uri') != '/example/pages/uri/' && $this->EE->input->post('pages_uri') != '')
        {
            if (isset($data['pages_template_id']) && is_numeric($data['pages_template_id']))
            {
                $site_id = $this->EE->config->item('site_id');
                
                $this->_cache['static_pages'][$site_id]['uris'][$this->entry_id]        = preg_replace("#[^a-zA-Z0-9_\-/\.]+$#i", '', str_replace($this->EE->config->item('site_url'), '', $data['pages_uri']));
                $this->_cache['static_pages'][$site_id]['uris'][$this->entry_id]        = '/'.ltrim($this->_cache['static_pages'][$site_id]['uris'][$this->entry_id], '/');
                $this->_cache['static_pages'][$site_id]['templates'][$this->entry_id]   = preg_replace("#[^0-9]+$#i", '', $data['pages_template_id']);

                if ($this->_cache['static_pages'][$site_id]['uris'][$this->entry_id] == '//')
                {
                    $this->_cache['static_pages'][$site_id]['uris'][$this->entry_id] = '/';
                }

                $this->EE->db->where('site_id', $this->EE->config->item('site_id'));
                $this->EE->db->update('sites', array('site_pages' => base64_encode(serialize($this->_cache['static_pages']))) );
            }
        }
        

        // Save revisions if needed
        
        if ($this->c_prefs['enable_versioning'] == 'y')
        {
            $this->EE->db->insert('entry_versioning', array(
                'entry_id'      => $this->entry_id,
                'channel_id'    => $this->channel_id,
                'author_id'     => $this->EE->session->userdata('member_id'),
                'version_date'  => $this->EE->localize->now,
                'version_data'  => serialize($data['revision_post'])
            ));
            
            $max = (is_numeric($this->c_prefs['max_revisions']) AND $this->c_prefs['max_revisions'] > 0) ? $this->c_prefs['max_revisions'] : 10;
            
            $this->EE->channel_entries_model->prune_revisions($this->entry_id, $max);
        }
        
        
        // Post update custom fields
        /* 
        Extending the core API only to change this query... ugh.
        $this->EE->db->select('field_id, field_name, field_label, field_type, field_required');
        $this->EE->db->join('channels', 'channels.field_group = channel_fields.group_id', 'left');
        $this->EE->db->where('channel_id', $this->channel_id);
        $query = $this->EE->db->get('channel_fields');
        */
        
        $sql = "SELECT cf.field_id, cf.field_name, cf.field_label, cf.field_type, cf.field_required 
                FROM exp_channels c
                LEFT JOIN exp_channel_fields cf ON (c.field_group = cf.group_id OR (cf.field_is_drifter = 'y' AND cf.drifter_channels LIKE '%{". $this->channel_id . "}%'))
                WHERE c.channel_id = $this->channel_id";

        $query = $this->EE->db->query($sql);

        if ($query->num_rows() > 0)
        {
            foreach ($query->result_array() as $row)
            {
                $field_name = $row['field_name'];
                $this->EE->api_channel_fields->settings[$row['field_id']]['entry_id'] = $this->entry_id;
                
                $this->EE->api_channel_fields->settings[$row['field_id']]['field_id'] = $row['field_id'];
                
                $fdata = isset($data[$field_name]) ? $data[$field_name] : '';
                $this->EE->api_channel_fields->setup_handler($row['field_id']);
                $this->EE->api_channel_fields->apply('post_save', array($fdata));               
            }
        }
    }

}