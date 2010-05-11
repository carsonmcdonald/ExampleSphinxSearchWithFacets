<?php

/*
Copyright (c) 2010 Carson McDonald

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License version 2
as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

*/

require_once('config.php');

class SearchResponse
{
  private $query_result = array();
  private $facet_results = array();

  private $enable_extended_info = false;
  private $disable_snippets = false;

  private $db_connection;
  private $snippet_stmt;

  private $errors = array();

  public function __construct($enable_extended_info, $disable_snippets, $db_connection)
  {
    $this->db_connection = $db_connection;

    $this->enable_extended_info = $enable_extended_info;
    $this->disable_snippets = $disable_snippets;
  }

  public function add_error($error)
  {
    $this->errors[] = $error;
  }

  public function set_result($query_result, $sphinx_client)
  {
    $this->query_result = $this->parse_result($query_result, $sphinx_client);
  }

  public function add_facet_result($query_result, $sphinx_client, $facet_name)
  {
    $this->facet_results[] = array('facet_name' => $facet_name, $this->parse_result($query_result, $sphinx_client, $facet_name));
  }

  public function get_response()
  {
    if( sizeof($this->errors) > 0 )
    {
      return json_encode(array('errors' => $this->errors));
    }
    else
    {
      $final_response = array();
      $final_response['query_results'] = $this->query_result;
      if(sizeof($this->facet_results) > 0)
        $final_response['facet_results'] = $this->facet_results;
      return json_encode($final_response);
    }
  }

  private function parse_result($query_result, $sphinx_client, $facet_name = null)
  {
    if( $query_result === false )
    {
      error_log('Unable to run query: ' . $sphinx_client->GetLastError());
      $this->errors[] = 'Unable to run query: ' . $sphinx_client->GetLastError();
      return null;
    } 
    else
    {
      $final_words = '';

      if(!$this->disable_snippets && $facet_name == null)
      {
        $this->snippet_stmt = $this->db_connection->prepare('select if(a.post_type_id = 1, a.title, b.title), a.body_text from post a left outer join post b on a.parent_id = b.id where a.id = ?');
      }

      $res_value = array();

      if( $sphinx_client->GetLastWarning() )
      {
        error_log('WARNING: ' . $sphinx_client-->GetLastWarning());
      }

      $res_value['query_result_total'] = $query_result['total'];
      $res_value['query_result_found'] = $query_result['total_found'];
      if($this->enable_extended_info)
      {
        $res_value['query_result_time'] = $query_result['time'];
      }

      if($query_result['total'] > 0)
      {
        if( is_array($query_result["words"]) )
        {
          $hit_arr = array();
          foreach( $query_result["words"] as $word => $info )
          {
            $final_words .= $word . ' ';
            $hit_arr[$word] = array('hits' => $info['hits'], 'documents' => $info['docs']);
          }
          $res_value['query_result_hits'] = $hit_arr;
        }

        if( is_array($query_result["matches"]) )
        {
          $result_arr = array();
          foreach( $query_result["matches"] as $docinfo )
          {
            if($this->enable_extended_info || $facet_name != null)
            {
              $attributes = array();
              foreach( $query_result["attrs"] as $attrname => $attrtype )
              {
                $value = $docinfo["attrs"][$attrname];

                if($attrname == 'tag_ids') continue;

                if($attrname == '@groupby') $attrname = 'facet_value';
                if($attrname == '@count') $attrname = 'count';

                if( $attrtype == SPH_ATTR_TIMESTAMP )
                {
                  $value = (int)$value;
                }

                $attributes[$attrname] = $value;
              }

              if($this->disable_snippets || $facet_name != null)
              {
                $result_arr[] = array('post_id' => $docinfo['id'], 'weight' => $docinfo['weight'], 'post_type' => (int)$docinfo["attrs"]['post_type_id'], 'attributes' => $attributes);
              }
              else
              {
                $result_arr[] = array('post_id' => $docinfo['id'], 'weight' => $docinfo['weight'], 'post_type' => (int)$docinfo["attrs"]['post_type_id'], 'attributes' => $attributes, 'snippet' => $this->get_snippet($docinfo['id'], (int)$docinfo["attrs"]['post_type_id']));
              }
            }
            else
            {
              if($this->disable_snippets || $facet_name != null)
              {
                $result_arr[] = array('post_id' => $docinfo['id'], 'weight' => $docinfo['weight'], 'post_type' => (int)$docinfo["attrs"]['post_type_id']);
              }
              else
              {
                $result_arr[] = array('post_id' => $docinfo['id'], 'weight' => $docinfo['weight'], 'post_type' => (int)$docinfo["attrs"]['post_type_id'], 'snippet' => $this->get_snippet($docinfo['id'], (int)$docinfo["attrs"]['post_type_id']));
              }
            }
          }
          $res_value['query_results'] = $result_arr;
        }
      }

      if(!$this->disable_snippets && $this->snippet_stmt != null && $facet_name == null)
      {
        $this->snippet_stmt->close();
        $this->snippet_stmt = null;

// TODO make this optional 
        $docs = array();
        foreach($res_value['query_results'] as $result_arr)
        {
          $docs[] = $result_arr['snippet']['title'];
          $docs[] = $result_arr['snippet']['body_text'];
        }

        $opts = array( "before_match" => "<b>", "after_match" => "</b>", "chunk_separator" => " ... ", "limit" => 250, "around" => 3 );
        $e_res = $sphinx_client->BuildExcerpts( $docs, 'so_2010_05', trim($final_words), $opts );
        if( !$e_res )
        {
          error_log("Error getting snippet excerpts: " . $sphinx_client->GetLastError());
        } 
        else
        {
          $count = 0;
          foreach($res_value['query_results'] as &$result_arr)
          {
            $result_arr['snippet']['title'] = $e_res[$count];
            $result_arr['snippet']['body_text'] = $e_res[$count+1];
            $count+=2;
          }
        }
      }
    }

    return $res_value;
  }

  private function get_snippet($post_id, $post_type_id)
  {
    $this->snippet_stmt->bind_param('i', $post_id);

    if($this->snippet_stmt->execute())
    {
      $this->snippet_stmt->bind_result($title, $body_text);
      if($this->snippet_stmt->fetch())
      {
        return array('title' => $title, 'body_text' => strip_tags($body_text));
      }
    }
  }
}

?>
