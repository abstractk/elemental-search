<?php
/**
 * Created by Nivanka Fonseka (nivanka@silverstripers.com).
 * User: nivankafonseka
 * Date: 6/2/18
 * Time: 12:06 PM
 * To change this template use File | Settings | File Templates.
 */

namespace SilverStripers\ElementalSearch\CMS\Search;

use BadMethodCallException;
use SilverStripe\Assets\File;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\CMS\Search\SearchForm as SS_SearchForm;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\Debug;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\PaginatedList;
use SilverStripers\ElementalSearch\Model\SearchDocument;

class SearchForm extends SS_SearchForm
{

    protected $classesToSearch = array(
        SearchDocument::class,
        File::class
    );

	public function classesToSearch($classes)
	{
		$supportedClasses = array(SearchDocument::class);

		$illegalClasses = array_diff($classes, $supportedClasses);
		if ($illegalClasses) {
			throw new BadMethodCallException(
				"SearchForm::classesToSearch() passed illegal classes '" . implode("', '", $illegalClasses)
				. "'.  At this stage, only File and SiteTree are allowed"
			);
		}
		$legalClasses = array_intersect($classes, $supportedClasses);
		$this->classesToSearch = $legalClasses;
	}
	
	/**
	 * Return dataObjectSet of the results using current request to get info from form.
	 * Wraps around {@link searchEngine()}.
	 *
	 * @return SS_List
	 */
	public function getResults()
	{
		// Get request data from request handler
		$request = $this->getRequestHandler()->getRequest();
		
		// set language (if present)
		$locale = null;
		$origLocale = null;
		if (class_exists('Translatable')) {
			$locale = $request->requestVar('searchlocale');
			if (SiteTree::singleton()->hasExtension('Translatable') && $locale) {
				if ($locale === "ALL") {
					Translatable::disable_locale_filter();
				} else {
					$origLocale = Translatable::get_current_locale();
					
					Translatable::set_current_locale($locale);
				}
			}
		}
		
		$keywords = $request->requestVar('Search');
		if( ctype_space($keywords) ){

			$list = new PaginatedList(new ArrayList([]));
			$list->setPageStart(0);
			$list->setPageLength(1);
			$list->setTotalItems(0);
			return $list;
		}
		
		$andProcessor = function ($matches) {
			return ' +' . $matches[2] . ' +' . $matches[4] . ' ';
		};
		$notProcessor = function ($matches) {
			return ' -' . $matches[3];
		};
		
		$keywords = preg_replace_callback('/()("[^()"]+")( and )("[^"()]+")()/i', $andProcessor, $keywords);
		$keywords = preg_replace_callback('/(^| )([^() ]+)( and )([^ ()]+)( |$)/i', $andProcessor, $keywords);
		$keywords = preg_replace_callback('/(^| )(not )("[^"()]+")/i', $notProcessor, $keywords);
		$keywords = preg_replace_callback('/(^| )(not )([^() ]+)( |$)/i', $notProcessor, $keywords);
		
		$keywords = $this->addStarsToKeywords($keywords);
		
		$pageLength = $this->getPageLength();
		$start = $request->requestVar('start') ?: 0;
		
		//add filters
		$request_vars = $request->getVars();
		$extraFilter = '';
		$this->extend('updateSearchFormFilters', $request_vars, $extraFilter);
		
		$extra_file_filter = '';
		$this->extend('updateExtraFileFilter', $request_vars, $extra_file_filter);
		
		$sort_by = "\"Relevance\" DESC";
		$this->extend('updateSortBy', $request_vars, $sort_by);
		
		$booleanSearch =
			strpos($keywords, '"') !== false ||
			strpos($keywords, '+') !== false ||
			strpos($keywords, '-') !== false ||
			strpos($keywords, '*') !== false;
		$results = DB::get_conn()->searchEngine($this->classesToSearch, $keywords, $start, $pageLength, $sort_by, $extraFilter, $booleanSearch, $extra_file_filter);
		
		// reset locale
		if (class_exists('Translatable')) {
			if (SiteTree::singleton()->hasExtension('Translatable') && $locale) {
				if ($locale == "ALL") {
					Translatable::enable_locale_filter();
				} else {
					Translatable::set_current_locale($origLocale);
				}
			}
		}
		
		return $results;
	}
	
}
