<?php

// HTTPFUL External library used for HTTP GET method.
// Web: http://phphttpclient.com
// Download it and put it in the same folder as this file.
// http://phphttpclient.com/downloads/httpful.phar
include('./httpful.phar');

/**
* Solution to the ib-red PHP test: GitHub API endpoints.
* Two endpoints for the GitHub API Using Httpful to speak to it.
*
* @author  Bernat Pericàs Serra. bernatpericasserra97@gmail.com
*/
class Endpoints {

	// GitHub API root url
	private const 	BASE 		= 'https://api.github.com';
	// Number of items to b received per page
	private const 	PERPAGE 	= 100;

	// Simple "Cache" (as an multidimensional array) to store previous queries. 
	// One position per organization, in which we store:
	// 	 One array of length = 2, in wich we store:
	// 		One Integer ("total")
	// 	    One Array ("list") with all repositories (as objects) of that org.
	private $repos = array();


	/**
	* First endpoint method.
	*
	* Get number of public repositories of an organization and 
	* its biggest repository.
	* The GitHub API v3 doesn't filter repositories by size, so we 
	* have to do it manually ().
 	*
	* @param  String 		$org 		required: 	Organizations name.
	* @return Array 								N. of repos and biggest one
	*/
	function repos_and_biggest($org){
		// First step: get the number of repositories.
		try{
			// Check if we have the repositories of this organization in cache.
			if(!array_key_exists($org, $this->repos)){
				// We don't have them, we have to get them.
				$this->total_repos($org);

				// Second step: get the name of the biggest repository.
				$this->all_repos($org);
			}		
			// Return results
			// The biggest repository will be the first one.
			return array('numero' => $this->repos[$org]['total'], 
				'nom_major' => $this->repos[$org]['list'][0]->name);
		}catch(Exception $e){
			echo("Error: ".$e->getMessage());
		}
	}

	/*
	* Get an organizations number of repos.
	*
	* Check if the organization exists in GitHub, if so, return the number
	* of public repositories it has.
	* Store the result in a class variable ('repos') to act as cache for 
	* future queries.
	*
	* @param  String 		$org 		required: 	organizations name.
	*
	* @throws \Exception 	when org name isn't found in GitHub.
	*/
	private function total_repos($org){
		// Set up the url to check.
		$url = self::BASE.'/orgs/'.$org;
		$response = $this->request($url);
		// Check if the organization exists.
		if (property_exists($response->body, 'message')) {
			echo $response;
			throw new Exception("Organization '".$org."' not found.");
		}
		// Save obtained number of repositories.
		$this->repos[$org]['total'] = $response->body->public_repos;
	}

	/*
	* Obtain all repositories from a given organization.
	* Sort them by its size.
	* Store the result in 'repos' class array.
	*
	* @param String 		$org 		required: 	organizations name.
	*/
	private function all_repos($org){
		$page = 1;
		$repos_array = $this->obtain_repos($org, $page);
				// If the organization has more than one repository, sort them.
		if (count($repos_array)>1) {
			$this->sort_desc($repos_array);
		}
		// Cache sorted repositories for future queries
		$this->repos[$org]['list'] = $repos_array;
	}

	/*
	* Second endpoint method.
	*
	* Return the N biggest repositories.
	* Note: The test statement doesn't specify any organization name as input 
	* of this endpoint, but it is considered as necessary. If no organization 
	* name is provided, the last one cached is returned.
	*
	* @param  Integer		$n 			required: 	number of repositories.
	* @param  String		$org 		optional: 	organizations name.
	*
	* @return Array 								N biggest repos.
	*
	* @throws \Exception 	when no org name is provided and cache is empty.
	*/
	function n_biggest($n, $org){
		try{
			// If NO organization name is provided.
			if ($org == null) {
				// If there is something in cache.
				if ($this->repos != null) {
					// Return the biggest repos of the last org added.
					return array_slice(end($this->repos)['list'], 0 ,$n);
				}else{
					// There is nothing in cache.
					throw new Exception("Please provide an organizations name");
				}
			// If organization name IS provided.
			// If it's not in cache.
			}elseif (!array_key_exists($org, $this->repos)) {
				// Check if organizations name exists in GitHub.
				// Save the total number of repositories for future queries 
				// at no extra cost.
				$this->total_repos($org);
				$this->all_repos($org);
			}
			// At this point it must be in cache.
			return $this->slice_n_names($this->repos[$org]['list'], $n);
		}catch(Exception $e){
			echo("Error: ".$e->getMessage());
		}
	}

	/**
	* Filter repository names from all other information.
	*
	* Slice the n first elements from an array of objects, from zero to $n
	* Extract the name of every object and put them in another array.
 	* NOTE: negative numbers ($n) will be made positive.
	* @param  Array 		$array 		required: 	array of objects.
	* @param  Integer 		$n 			required: 	index to be cut off.
	*
	* @return Array 								N biggest repos.
	*/	
	private function slice_n_names($array, $n){
		// Get n biggest repositories.
		$n_biggest = array_slice($array, 0, abs($n));
		// Extract the names from them.
		$n_names = array();
		foreach ($n_biggest as $repo) {
			array_push($n_names, $repo->name);
		}
		return $n_names;
	}

	/**
	* GET request. 
	*
	* Make an HTTP get request using Httpful Library.
	* Return it to the function it was called from.
 	*
	* @param  String 		$url 		required: 	url to get.
	* 
	* @return Object 					depends on the $url.
	*/	
	private function request($url){
		return \Httpful\Request::get($url)
		//->addHeader('Authorization', 'xxx')
		->Header('User-Agent: PHP Test for ib-red')
		->send();
	}

	/**
	* Get all repositories from an organization recursively.
	*
	* GitHub API serves at most 100 results per page, so we have to make 
	* multiple calls sometimes. 
	* There isn't an elegant solution to make concurrent HTTP requests in PHP.
 	*
	* @param  String 		$org 		required: 	organizations name.
	* @param  String 		$page 		required: 	page to request.
	*
	* @return Array 		$array_obj 	repos of the last page.
	* @return Array 		"merge" 	merged repos.
	*/	
	private function obtain_repos($org, $page){
		$url = self::BASE.'/orgs/'.$org.'/repos?page='.$page.'&per_page='.self::PERPAGE;
		$response = $this->request($url);
		// Extract the array of repositories from the response object.
		$array_obj = $response->body;
		// Base case: last page
		if (count($array_obj) < self::PERPAGE){
			// End recursion.
			return $array_obj;
		} else{
			// Recursive call to check the next page of results.
			return array_merge($array_obj, $this->obtain_repos($org, $page+1));
		}
	}

	/**
	* Sort an array of objects by its property 'size'.
 	*
	* @param  Array 		$array 		required: 	array to be sorted.
	*
	* @return Boolean 					if a's size is smaller than b's
	*/	
	private function sort_desc($array){
		usort($array,function($a,$b){
			// Custom function.
			return $a->size < $b->size;
		});
	}
}


//////////////////////////////////// TESTS ////////////////////////////////////


header('Content-Type: application/json; charset=UTF-8');

echo "\n-----Endpoint 1-----\n";
$end = new Endpoints();
$organization = "facebook";
$results = $end->repos_and_biggest($organization);
echo "L'organització '".$organization."' té ".$results['numero']." repositoris 
públics, el major dels quals s'anomena '".$results['nom_major']."'.\n\n";

$end = new Endpoints();
$organization = "github";
$results = $end->repos_and_biggest($organization);
echo "L'organització '".$organization."' té ".$results['numero']." repositoris 
públics, el major dels quals s'anomena '".$results['nom_major']."'.\n\n";

echo "\n-----Endpoint 2-----\n";
$organization = "github";
$n = 50;
$results = $end->n_biggest($n, $organization);
echo "Els ".$n." repositoris més grans de ".$organization." són: \n\n";
print_r($results);

?>