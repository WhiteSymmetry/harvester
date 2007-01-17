{**
 * index.tpl
 *
 * Copyright (c) 2005-2007 Alec Smecher and John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * About the site.
 *
 * $Id$
 *}

{assign var="pageTitle" value="navigation.about"}
{include file="common/header.tpl"}
{if !empty($about)}
	<p>{$about|nl2br}</p>
{/if}

<a href="{url op="harvester"}">{translate key="about.harvester"}</a>

{include file="common/footer.tpl"}
