<?php
/**
 * Joomla! System plugin - Language Domains
 *
 * @author Yireo (info@yireo.com)
 * @copyright Copyright 2012 Yireo.com. All rights reserved
 * @license GNU Public License
 * @link http://www.yireo.com
 */

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

jimport( 'joomla.plugin.plugin' );

/**
 * @package Joomla!
 * @subpackage System
 */
class plgSystemLanguageDomains extends JPlugin
{
    /**
     * Event onAfterInitialise
     *
     * @access public
     * @param null
     * @return null
     */
	public function onAfterInitialise()
    {
        // If this is the Administrator-application, or if debugging is set, do nothing
        $application = JFactory::getApplication();
        if($application->isAdmin()) {
            return;
        }

        // Disable browser-detection
        $application->setDetectBrowser(false);

        // Detect the language
        $languageTag = JFactory::getLanguage()->getTag();
        $languageInput = JRequest::getString('language');

        // Get the bindings
        $bindings = $this->getBindings();

        // Check for the binding of the current language
        if(!empty($languageInput)) {
            if(isset($bindings[$languageTag])) {
                $domain = $bindings[$languageTag];
                if(stristr(JURI::current(), $domain) == false) {

                    // Add URL-elements to the domain
                    $domain = $this->getUrlFromDomain($domain);

                    // Replace the current domain with the new domain
                    $currentUrl = JURI::current();
                    $newUrl = str_replace(JURI::base(), $domain, $currentUrl);

                    // Strip out the sef-language-part
                    $languages = JLanguageHelper::getLanguages('sef');
                    foreach($languages as $languageSef => $language) {
                        if($language->lang_code == $languageTag) {
                            //$newUrl = str_replace('/'.$languageSef.'/', '/', $newUrl); // @todo: This d
                            break;
                        }
                    }

                    // Set the cookie
                    $conf = JFactory::getConfig();
                    $cookie_domain  = $conf->get('config.cookie_domain', '');
                    $cookie_path    = $conf->get('config.cookie_path', '/');
                    setcookie(JApplication::getHash('language'), $languageTag, time() + 365 * 86400, $cookie_path, $cookie_domain);

                    // Redirect
                    $application->redirect($newUrl);
                    $application->close();
                }
            }
        } else {

            // Check if the current default language is correct
            foreach($bindings as $languageCode => $domain) {
                if(stristr(JURI::current(), $domain) == true) {

                    // Set the cookie
                    $conf = JFactory::getConfig();
                    $cookie_domain  = $conf->get('config.cookie_domain', '');
                    $cookie_path    = $conf->get('config.cookie_path', '/');
                    setcookie(JApplication::getHash('language'), $languageCode, time() + 365 * 86400, $cookie_path, $cookie_domain);

                    // Change the current default language
                    JRequest::setVar('language', $languageCode);
                    JFactory::getLanguage()->setDefault($languageCode);
                    JFactory::getLanguage()->setLanguage($languageCode);

                    break;
                }
            }
        }
    }

    /**
     * Event onAfterRender
     *
     * @access public
     * @param null
     * @return null
     */
	public function onAfterRender()
    {
        // If this is the Administrator-application, or if debugging is set, do nothing
        $application = JFactory::getApplication();
        if($application->isAdmin() || JDEBUG) {
            return;
        }

        // Fetch the document buffer
        $buffer = JResponse::getBody();
    
        // Get the bindings
        $bindings = $this->getBindings();

        // Loop through the languages and check for any URL
        $languages = JLanguageHelper::getLanguages('sef');
        foreach($languages as $languageSef => $language) {

            $languageCode = $language->lang_code;
            if(!isset($bindings[$languageCode])) continue;
            $domain = $bindings[$languageCode];
            $domain = $this->getUrlFromDomain($domain).$languageSef.'/';

            // Replace full URLs
            if(preg_match_all('/([^\'\"]+)\/'.$languageSef.'\//', $buffer, $matches)) {
                foreach($matches[0] as $match) {
                    $buffer = str_replace($match, $domain, $buffer);
                }
            }

            // Replace shortened URLs
            if(preg_match_all('/([\'\"]{1})\/('.$languageSef.')\//', $buffer, $matches)) {
                foreach($matches[0] as $index => $match) {
                    $buffer = str_replace($match, $matches[1][$index].$domain, $buffer);
                }
            }
        }
        
        JResponse::setBody($buffer);
    }

    /**
     * Method to get the bindings for languages
     *
     * @access public
     * @param null
     * @return null
     */
	protected function getBindings()
    {
        $bindings = trim($this->params->get('bindings'));
        if(empty($bindings)) {
            return array();
        }
    
        $bindingsArray = explode("\n", $bindings);
        $bindings = array();
        foreach($bindingsArray as $index => $binding) {
            $binding = explode('=', $binding);
            if(isset($binding[0]) && isset($binding[1])) {

                $languageCode = trim($binding[0]);
                $languageCode = str_replace('_', '-', $languageCode);

                $domain = trim($binding[1]);

                $bindings[$languageCode] = $domain;
            }
        }

        return $bindings;
    }

    /* 
     * Helper-method to get a proper URL from the domain
     *
     * @access public
     * @param string
     * @return string
     */
	protected function getUrlFromDomain($domain)
    {
        // Add URL-elements to the domain
        if(preg_match('/^(http|https):\/\//', $domain) == false) $domain = 'http://'.$domain;
        if(preg_match('/\/$/', $domain) == false) $domain = $domain.'/';
        return $domain;
    } 
}
