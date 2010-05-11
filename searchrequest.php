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

/*
  Input value ids:

  'q' => 'java', // query
  'o' => '0', // offset
  'l' => '10', // limit
  'w' => '100,10,1', // weights
  'f' => 'ownerId(1,2,3),created(1270574334, 1273166334)', // filters
  't' => 'weekCreated,postType' // facets
  'e' => true enable extended info
  'd' => false disable snippets

   Valid filters:
     [!]ownerId(...)
     [!]created(<start>,<end>)
     [!]postType([A|Q])
     [!]score(<start>,<end>)
     [!]views(<start>,<end>)
     [!]favorites(<start>,<end>)
     [!]hasAcceptedAnswer
     [!]tags(c#,java,c++,etc)

   Valid facets:
      weekCreated,monthCreated,yearCreated,postType,postScore,postViews,postFavorites,accepted,tag
 */

class SearchRequest
{
  private $offset;
  private $limit;
  private $query;
  private $weights;
  private $filters;
  private $facets;
  private $enable_extended_info = false;
  private $disable_snippets = false;

  private $db_connection;

  private $errors = array();

  function __construct($input_value, $db_connection)
  {
    $this->db_connection = $db_connection;

    // Process the query value
    if(isset($input_value['q']))
      $this->query = $input_value['q'];
    else
      $this->errors[] = 'Query is a required parameter.'; 

    // Process the offset value
    if(!isset($input_value['o']))
      $this->offset = 0;
    else if(is_numeric($input_value['o']))
      $this->offset = (int)$input_value['o'];
    else
      $this->errors[] = 'Offset must be an integer.'; 

    // Process the limit value
    if(!isset($input_value['l']))
      $this->limit = 100;
    else if(is_numeric($input_value['l']))
    {
      $this->limit = (int)$input_value['l'];
      if($this->limit > MAX_LIMIT)
        $this->errors[] = 'Limit must be less than or equal to ' . MAX_LIMIT . '.'; 
    }
    else
      $this->errors[] = 'Limit must be an integer.'; 

    // Parse the weight values
    if(isset($input_value['w']))
      $this->weights = $this->parse_weights($input_value['w']);
    else
      $this->weights = array( 'title' => 100, 'body_text' => 40, 'comments' => 10, 'tags' => 70);

    // Process the filter values
    if(isset($input_value['f']))
      $this->filters = $this->parse_filters($input_value['f']);
    else
      $this->filters = array();

    // Process the facet values
    if(isset($input_value['t']))
      $this->facets = $this->parse_facets($input_value['t']);
    else
      $this->facets = array();

    // Process info flags
    if(isset($input_value['e']))
      $this->enable_extended_info = true;
    
    if(isset($input_value['d']))
      $this->disable_snippets = true;
  }

  public function has_error()
  {
    return sizeof($this->errors) > 0;
  }

  public function get_errors()
  {
    return $this->errors;
  }

  public function configure_client($sphinx_client)
  {
    $this->apply_filters($sphinx_client, $this->filters);

    $sphinx_client->SetFieldWeights( $this->weights );

    $sphinx_client->SetMatchMode( SPH_MATCH_EXTENDED2 );
    $sphinx_client->SetRankingMode( SPH_RANK_PROXIMITY_BM25 );

    $sphinx_client->SetLimits($this->offset, $this->limit, ( $this->limit > 1000 ) ? $this->limit : 1000);
  }

  public function get_query()
  {
    return $this->query;
  }

  public function should_enable_extended_info()
  {
    return $this->enable_extended_info;
  }

  public function should_disable_snippets()
  {
    return $this->disable_snippets;
  }

  public function has_facets()
  {
    return $this->facets != null && sizeof($this->facets) > 0;
  }

  public function get_facets()
  {
    return $this->facets;
  }

  private function parse_weights($weight_value)
  {
    $weights = array();

    $sev = preg_split('/,/', $weight_value);
    if(sizeof($sev) != 4)
    {
      $this->errors[] = 'Weight values must be in the following format: <title weight>,<text weight>,<comment weight>,<tag weight>';
    }
    else
    {
      foreach($sev as $v) if(!is_numeric($v)) $this->errors[] = 'Weight values must be an integer.';

      array( 'title' => (int)$sev[0], 'body_text' => (int)$sev[1], 'comments' => (int)$sev[2], (int)$sev[3] ); 
    }

    return $weights;
  }

  private function apply_filters($sphinx_client, $filters)
  {
    foreach($filters as $filter)
    {
      switch($filter[0])
      {
        case 'owner_id':
          $sphinx_client->SetFilter('owner_id', $filter[1], $filter[2]);
        break;
        case 'date_added':
          $sphinx_client->SetFilterRange('date_added', $filter[1], $filter[2], $filter[3]);
        break;
        case 'post_type_id':
          $sphinx_client->SetFilter('post_type_id', $filter[1], $filter[2]);
        break;
        case 'post_score':
          $sphinx_client->SetFilterRange('post_score', $filter[1], $filter[2], $filter[3]);
        break;
        case 'post_favorite_count':
          $sphinx_client->SetFilterRange('post_favorite_count', $filter[1], $filter[2], $filter[3]);
        break;
        case 'post_view_count':
          $sphinx_client->SetFilterRange('post_view_count', $filter[1], $filter[2], $filter[3]);
        break;
        case 'has_accepted_answer':
          $sphinx_client->SetFilter('has_accepted_answer', $filter[1], $filter[2]);
        break;
        case 'tag_ids':
          $sphinx_client->SetFilter('tag_ids', $filter[1], $filter[2]);
        break;
      }
    }
  }

  private function parse_filter($type_id, $default_value, $filter_value, &$filters)
  {
    $av = null;
    preg_match('/\\((.*)\\)/', $filter_value, $av);
    if($av != null && sizeof($av) == 2)
    {
      $sev = preg_split('/,/', $av[1]);
      if(sizeof($sev) == 2)
      {
        $filters[] = array($type_id, trim($sev[0]), trim($sev[1]), $filter_value[0] == '!');
      }
      else
      {
        $filters[] = array($type_id, trim($sev[0]), $default_value, $filter_value[0] == '!');
      }
    }
  }

  private function parse_filters($filter_string)
  {
    $filter_regexes = array(
         'owner_id' => '/[!]?ownerId\\(.*?\\)/i',
         'date_added' => '/[!]?created\\(.*?\\)/i',
         'post_type_id' => '/[!]?postType\\(.*?\\)/i',
         'post_score' => '/[!]?score\\(.*?\\)/i',
         'post_view_count' => '/[!]?views\\(.*?\\)/i',
         'post_favorite_count' => '/[!]?favorites\\(.*?\\)/i',
         'has_accepted_answer' => '/[!]?hasAcceptedAnswer/i',
         'tag_ids' => '/[!]?tags\\(.*?\\)/i',
    );

    $raw_filters = array();
    foreach($filter_regexes as $filter_id => $filter_regex)
    {
      $tmp_filters = null;
      preg_match($filter_regex, $filter_string, $tmp_filters);
      $raw_filters[$filter_id] = $tmp_filters == null ? null : $tmp_filters[0];
    }

    $filters = array();
    foreach($raw_filters as $filter_id => $filter_value)
    {
      if($filter_value == null) continue;

      switch($filter_id)
      {
        case 'owner_id':
          $av = null;
          preg_match('/\\((.*)\\)/', $filter_value, $av);
          if($av != null && sizeof($av) == 2)
          {
            $filters[] = array('owner_id', preg_split('/,/', $av[1]), $filter_value[0] == '!');
          }
          else
          {
            $this->errors[] = 'Wrong format for ownerId filter.';
          }
        break;
        case 'post_type_id':
          $av = null;
          preg_match('/\\((.*)\\)/', $filter_value, $av);
          if($av != null && sizeof($av) == 2)
          {
            $filters[] = array('post_type_id', array(strtoupper(trim($av[1])) == 'Q' ? 1 : 2), $filter_value[0] == '!');
          }
          else
          {
            $this->errors[] = 'Wrong format for postType filter.';
          }
        break;
        case 'post_score':
          $this->parse_filter('post_score', 10000000, $filter_value, $filters, false);
        break;
        case 'date_added':
          $this->parse_filter('date_added', time(), $filter_value, $filters, false);
        break;
        case 'post_view_count':
          $this->parse_filter('post_view_count', 10000000, $filter_value, $filters, false);
        break;
        case 'post_favorite_count':
          $this->parse_filter('post_favorite_count', 10000000, $filter_value, $filters, false);
        break;
        case 'has_accepted_answer':
          if($filter_value != null)
          {
            $filters[] = array('has_accepted_answer', array(true), $filter_value[0] == '!');
          }
        break;
        case 'tag_ids':
          $av = null;
          preg_match('/\\((.*)\\)/', $filter_value, $av);
          if($av != null && sizeof($av) >= 2)
          {
            $sev = preg_split('/,/', $av[1]);
  
            if($sev != null && sizeof($sev) > 0)
            {
              $sev_j = array();
              $sev_i = array();
              for($i=0; $i<sizeof($sev) && $i<MAX_TAGS; $i++) 
              {
                $sev_j[] = '?'; 
                $sev_i[] = 's'; 
                $sev[$i] = trim(strtolower($sev[$i]));
              }
  
              $tag_query = "select id, name from tag where name in (" . implode(',', $sev_j) . ")";
  
              $tag_stmt = $this->db_connection->prepare($tag_query);
              call_user_func_array(array($tag_stmt, 'bind_param'), $this->ref_values(array_merge(array(implode('', $sev_i)), $sev)));
  
              $tag_ids = array();
              if($tag_stmt->execute())
              {
                $tag_stmt->bind_result($tag_id, $tag_name);
                while ($tag_stmt->fetch())
                {
                  $tag_ids[] = $tag_id;
                }

                if(sizeof($tag_ids) > 0)
                {
                  $filters[] = array('tag_ids', $tag_ids, $filter_value[0] == '!');
                }
              }
              else
              {
                $this->errors[] = 'Could not find given tags.';
                error_log("DB error finding tag ids: " . $tag_stmt->error); 
              }

              $tag_stmt->close();
            }
            else
            {
              $this->errors[] = 'Wrong format for tag filter.';
            }
          }
          else
          {
            $this->errors[] = 'Wrong format for tag filter.';
          }
        break;
        default:
          $this->errors[] = 'Uknown filter type: ' . $filter_id;
        break;
      }
    }

    return $filters;
  }

  private function parse_facets($facets_value)
  {
    $facets = array();
 
    $facets_raw = preg_split('/,/', $facets_value);
    foreach($facets_raw as $facet_value)
    {
      switch($facet_value)
      {
        case 'weekCreated':
          $facets['date_added'] = SPH_GROUPBY_WEEK;
        break;
        case 'monthCreated':
          $facets['date_added'] = SPH_GROUPBY_MONTH;
        break;
        case 'yearCreated':
          $facets['date_added'] = SPH_GROUPBY_YEAR;
        break;
        case 'postType':
          $facets['post_type_id'] = SPH_GROUPBY_ATTR;
        break;
        case 'postScore':
          $facets['post_score'] = SPH_GROUPBY_ATTR;
        break;
        case 'postViews':
          $facets['post_view_count'] = SPH_GROUPBY_ATTR;
        break;
        case 'postFavorites':
          $facets['post_favorite_count'] = SPH_GROUPBY_ATTR;
        break;
        case 'accepted':
          $facets['has_accepted_answer'] = SPH_GROUPBY_ATTR;
        break;
        case 'tag':
          $facets['tag_ids'] = SPH_GROUPBY_ATTR;
        break;
        default:
          $this->errors[] = 'Unknown facet type: ' . $facet_value;
        break;
      }
    }

    return $facets;
  }

  private function ref_values($arr)
  {
    if (strnatcmp(phpversion(),'5.3') >= 0) //Reference is required for PHP 5.3+
    {
      $refs = array();
      foreach($arr as $key => $value)
      {
        $refs[$key] = &$arr[$key];
      }
      return $refs;
    }
    return $arr;
  } 
}

?>
