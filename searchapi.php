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

require_once('sphinxapi.php');

require_once('searchrequest.php');
require_once('searchresponse.php');

$db_connection = new mysqli(MYSQL_SERVER_HOST, MYSQL_SERVER_USER, MYSQL_SERVER_PASS, MYSQL_SERVER_DB);

$search_request = new SearchRequest($_REQUEST, $db_connection);
$search_response = new SearchResponse($search_request->should_enable_extended_info(), $search_request->should_disable_snippets(), $db_connection);

if($search_request->has_error())
{
  foreach($search_request->get_errors() as $error)
  {
    $search_response->add_error($error);
  }
}
else
{
  $sphinx_client = new SphinxClient();
  $sphinx_client->SetServer(SPHINX_SERVER_HOST, SPHINX_SERVER_PORT);
  $sphinx_client->SetConnectTimeout(SPHINX_SERVER_TOUT);
  $sphinx_client->SetArrayResult(true);

  $search_request->configure_client($sphinx_client);

  $query_result = $sphinx_client->Query( $search_request->get_query(), '*' );

  $search_response->set_result($query_result, $sphinx_client);

  if($query_result['total_found'] > 0 && $search_request->has_facets())
  {
    if($query_result['total_found'] > 1000)
    {
      $search_response->add_error("Too many search results for facets.");
    }
    else
    {
      $sphinx_client->SetLimits(0, MAX_FACET_SIZE);
      foreach($search_request->get_facets() as $facet_id => $facet_type)
      {
        $sphinx_client->ResetGroupBy();
        $sphinx_client->SetGroupBy($facet_id, $facet_type, '@count desc');

        $query_result = $sphinx_client->Query( $search_request->get_query(), '*' );

        $search_response->add_facet_result($query_result, $sphinx_client, $facet_id);
      }
    }
  }
}

header('Content-type: application/json');

echo $search_response->get_response();

$db_connection->close();

?>
