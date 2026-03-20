<?php

declare(strict_types=1);

/**
 * Example: Working with the ps_linklist PrestaShop module.
 *
 * ps_linklist manages groups of footer links (e.g., "Products", "Our Company",
 * "Your Account"). Each link block has a title and a configurable list of
 * internal CMS page links, category links, and custom URLs.
 *
 * This file documents common usage patterns.
 */

// --- The module renders automatically in the footer ---
// Hooks: displayFooter, displayLeftColumn, displayRightColumn
// PrestaShop dispatches hooks automatically during page rendering.

// --- Widget invocation in Smarty/Twig template ---
// {widget name="ps_linklist" hook="displayFooter"}

// --- Back Office configuration ---
// Modules > Link List:
//   - Create named link blocks (e.g., "Products", "Company", "Help")
//   - Add links to each block:
//     - CMS pages (e.g., About Us, Legal Notice, Contact)
//     - Product categories
//     - Static pages (My Account, Orders, Addresses)
//     - Custom URLs with custom labels
//   - Choose hook placement for each block
//   - Reorder blocks and links via drag-and-drop

// --- Querying link blocks programmatically ---
// Link block data is stored in ps_linklist_block (hook) + ps_linklist_block_lang (titles/links):
//
// $linkBlocks = Db::getInstance()->executeS(
//     'SELECT lb.id_link_block, lbl.name, lbl.content
//      FROM `' . _DB_PREFIX_ . 'link_block` lb
//      JOIN `' . _DB_PREFIX_ . 'link_block_lang` lbl
//        ON lb.id_link_block = lbl.id_link_block
//      WHERE lbl.id_lang = ' . (int) Context::getContext()->language->id
// );
//
// foreach ($linkBlocks as $block) {
//     $links = json_decode($block['content'], true);
//     echo $block['name'] . ":\n";
//     foreach ($links as $link) {
//         echo "  " . $link['label'] . " => " . $link['url'] . "\n";
//     }
// }

// --- Template override ---
// themes/{theme}/modules/ps_linklist/views/templates/hook/ps_linklist.tpl
