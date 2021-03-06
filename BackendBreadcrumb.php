<?php

/**
 * zz_bebreadcrumb Extension for Contao Open Source CMS
 *
 * @copyright  Copyright (c) 2009-2014, terminal42 gmbh
 * @author     terminal42 gmbh <info@terminal42.ch>
 * @license    http://opensource.org/licenses/lgpl-3.0.html LGPL
 * @link       http://github.com/terminal42/contao-zz_bebreadcrumb
 */


class BackendBreadcrumb extends Backend
{
    /**
     * __construct() of Class Backend is protected...
     */
    public function __construct()
    {
        parent::__construct();
    }


    /**
     * Generate the backend breadcrumb
     */
    public function generate($strBuffer, $strTemplate)
    {
        if ($strTemplate != 'be_main') {
            return $strBuffer;
        }

        $do = $this->Input->get('do');
        $moduleGroup = $this->findBackendModuleGroup($do);
        $id = $this->Input->get('id');
        $skipFirst = $this->Input->get('act') == '';
        $levels = array();

        if (strlen($do) && strlen($id)) {
            $parent = true;
            $tables = $GLOBALS['BE_MOD'][$moduleGroup][$do]['tables'];
            $table = $this->Input->get('table') ?: $tables[0];

            while ($parent) {
                $this->loadDataContainer($table);
                $this->loadLanguageFile($table);

                $ptable = $GLOBALS['TL_DCA'][$table]['config']['ptable'];

                $level = array
                (
                    'id'          => $id,
                    'table'       => $table,
                    'moduleGroup' => $moduleGroup,
                    'href'        => $GLOBALS['TL_DCA'][$table]['list']['operations']['edit']['href'],
                );

                if ($GLOBALS['TL_DCA'][$table]['config']['dataContainer'] != 'Table') {
                    if ($do == 'tasks') {
                        $this->loadLanguageFile('tl_task');
                        $level['label'] = sprintf($GLOBALS['TL_LANG']['tl_task']['edit'][1], $id);
                    } else {
                        $level['label'] = $id;
                    }
                } else {
                    if ($skipFirst && $ptable != '') {
                        $skipFirst = false;
                        $table = $ptable;
                        continue;
                    }

                    if (in_array($table, $tables))
                        $level['do'] = $do;

                    $row = $this->Database->prepare("SELECT * FROM " . $table . " WHERE id=" . $id)
                        ->limit(1)
                        ->execute()
                        ->fetchAssoc();

                    $level['row'] = $row;

                    if ($GLOBALS['TL_DCA'][$table]['list']['label']['fields'] && count($GLOBALS['TL_DCA'][$table]['list']['label']['fields'])) {
                        $showFields = $GLOBALS['TL_DCA'][$table]['list']['label']['fields'];

                        // Label
                        foreach ($showFields as $k => $v) {
                            if (in_array($GLOBALS['TL_DCA'][$table]['fields'][$v]['flag'], array(5, 6, 7, 8, 9, 10))) {
                                $labels[$k] = date($GLOBALS['TL_CONFIG']['datimFormat'], $row[$v]);
                            } elseif ($GLOBALS['TL_DCA'][$table]['fields'][$v]['inputType'] == 'checkbox' && !$GLOBALS['TL_DCA'][$table]['fields'][$v]['eval']['multiple']) {
                                $labels[$k] = strlen($row[$v]) ? $GLOBALS['TL_DCA'][$table]['fields'][$v]['label'][0] : '';
                            } else {
                                $row_v = deserialize($row[$v]);

                                if (is_array($row_v)) {
                                    $args_k = array();

                                    foreach ($row_v as $option) {
                                        $args_k[] = strlen($GLOBALS['TL_DCA'][$table]['fields'][$v]['reference'][$option]) ? $GLOBALS['TL_DCA'][$table]['fields'][$v]['reference'][$option] : $option;
                                    }

                                    $labels[$k] = implode(', ', $args_k);
                                } elseif ($GLOBALS['TL_DCA'][$table]['fields'][$v]['reference'][$row[$v]]) {
                                    $labels[$k] = is_array($GLOBALS['TL_DCA'][$table]['fields'][$v]['reference'][$row[$v]]) ? $GLOBALS['TL_DCA'][$table]['fields'][$v]['reference'][$row[$v]][0] : $GLOBALS['TL_DCA'][$table]['fields'][$v]['reference'][$row[$v]];
                                } else {
                                    $labels[$k] = $row[$v];
                                }
                            }
                        }

                        // Shorten label it if it is too long
                        $label = vsprintf((strlen($GLOBALS['TL_DCA'][$table]['list']['label']['format']) ? $GLOBALS['TL_DCA'][$table]['list']['label']['format'] : '%s'), $labels);

                        if (strlen($GLOBALS['TL_DCA'][$table]['list']['label']['maxCharacters']) && $GLOBALS['TL_DCA'][$table]['list']['label']['maxCharacters'] < strlen($label)) {
                            $label = trim(utf8_substr($label, 0, $GLOBALS['TL_DCA'][$table]['list']['label']['maxCharacters'])) . '...';
                        }

                        // Remove empty brackets (), [], {}, <> and empty tags from label
                        $label = preg_replace('/\(\) ?|\[\] ?|\{\} ?|<> ?/i', '', $label);
                        $label = preg_replace('/<[^>]+>\s*<\/[^>]+>/i', '', $label);
                    } else if ($GLOBALS['TL_DCA'][$table]['list']['sorting']['mode'] == 4 && $GLOBALS['TL_DCA'][$table]['list']['sorting']['child_record_callback']) {
                        switch ($table) {
                            case 'tl_content':
                            case 'tl_form_field':
                            case 'tl_style':
                                if ($this->Input->get('act') == 'edit' && $this->Input->get('id') == $id) {
                                    $label = sprintf($GLOBALS['TL_LANG']['MSC']['editRecord'], 'ID ' . $id);
                                    break;
                                }

                            default:
                                $callback_class = $GLOBALS['TL_DCA'][$table]['list']['sorting']['child_record_callback'][0];
                                $callback_func = $GLOBALS['TL_DCA'][$table]['list']['sorting']['child_record_callback'][1];
                                $this->import($callback_class);
                                $label = $this->{$callback_class}->$callback_func($row);

                                preg_match('((.*?)(</div>|<br[ /]*>))', $label, $match);
                                $label = strip_tags($match[0]);
                                break;
                        }
                    }
                    $level['label'] = $label;
                }

                $levels[] = $level;

                if ($ptable != '') {
                    $table = $ptable;
                    $id = $row['pid'];
                } else {
                    $parent = false;
                }
            }

            krsort($levels);
        }

        $strTheme = \Backend::getTheme();
        $strMenu = '<div id="mod_backendbreadcrumb" style="text-align: left; display: none">';

        $strMenu .= sprintf(
            '<a href="%s" class="navigation home" title="%s" style="background-image:url(\'system/themes/%s/images/home.gif\');">%s</a>',
            $this->Environment->script,
            $GLOBALS['TL_LANG']['MSC']['homeTitle'],
            $strTheme,
            $GLOBALS['TL_LANG']['MSC']['home']
        );


        if (strlen($do)) {
            $icon = $GLOBALS['BE_MOD'][$moduleGroup][$do]['icon'];
            if (strlen($icon))
                $style = ' style="background-image: url(' . $icon . ')"';

            $href = $this->Environment->script . '?do=' . $do;
            $strMenu .= ' &raquo; <a href="' . $href . '" class="navigation ' . $do . '"' . $style . '>' . $GLOBALS['TL_LANG']['MOD'][$do][0] . '</a>';

            if (count($levels)) {
                foreach ($levels as $level) {
                    $style = '';
                    switch ($level['table']) {
                        case 'tl_page':
                            $style = ' style="background:url(system/themes/' . $strTheme . '/images/' . $row['type'] . '.gif) no-repeat left center; padding-left: 20px"';
                            break;

                        case 'tl_article':
                            $style = ' style="background:url(system/themes/' . $strTheme . '/images/articles.gif) no-repeat left center; padding-left: 20px"';
                            break;
                    }

                    // Level is part of this module
                    if (strlen($level['do'])) {
                        $href = $this->addToUrl($level['href'] . '&amp;id=' . $level['id'], $href);
                        $strMenu .= ' &raquo; <a href="' . $href . '"' . $style . '>' . $level['label'] . '</a>';
                    } // Level is not part of this module. Do not link.
                    else {
                        $strMenu .= ' &raquo; <span' . $style . '>' . $level['label'] . '</span>';
                    }
                }
            }
        }

        $strMenu .= '</div>';

        $strBuffer = str_replace('<div id="tl_navigation">', '<div id="tl_navigation">'.$strMenu, $strBuffer);

        return $strBuffer;
    }

    /**
     * Find a particular backend module
     */
    protected function findBackendModuleGroup($strModule)
    {
        foreach ($GLOBALS['BE_MOD'] as $moduleGroup => $modules) {
            if (in_array($strModule, array_keys($modules)))
                return $moduleGroup;
        }

        return false;
    }

    /**
     * Overwrite parent method to allow any URL
     * @param   string
     * @return  string
     */
    public static function addToUrl($strRequest, $strUrl = '')
    {
        $queryString = explode('?', $strUrl);
        $queryString = $queryString[1];

        $strRequest = preg_replace('/^&(amp;)?/i', '', $strRequest);
        $queries = preg_split('/&(amp;)?/i', $queryString);

        // Overwrite existing parameters
        foreach ($queries as $k => $v) {
            $explode = explode('=', $v);

            if (preg_match('/' . preg_quote($explode[0], '/') . '=/i', $strRequest)) {
                unset($queries[$k]);
            }
        }

        $href = '?';

        if (count($queries) > 0) {
            $href .= implode('&amp;', $queries) . '&amp;';
        }

        return \Environment::get('base') . \Environment::get('script') . $href . str_replace(' ', '%20', $strRequest);
    }

}

