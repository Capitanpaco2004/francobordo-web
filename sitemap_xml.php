<?php
ob_start();
require('includes/application_top.php');

function TiM_sitemap_catalog($parent_id=0, $cPath='') {
  global $output;
  $categories_query = tep_db_query("select categories_id from ". TABLE_CATEGORIES ." where parent_id = '". $parent_id ."' order by categories_id;");
  while ($category=tep_db_fetch_array($categories_query)) {
    $products_query = tep_db_query("select p.products_id, p.products_last_modified from ". TABLE_PRODUCTS ." p left join ". TABLE_PRODUCTS_TO_CATEGORIES ." p2c on (p2c.products_id = p.products_id) where p2c.categories_id = '". $category['categories_id'] ."' and p.products_status = 1;");
    if (tep_db_num_rows($products_query) > 0) {
    $output .= '  <url>' . PHP_EOL
             . '    <loc>'. tep_href_link(FILENAME_CATEGORIES, 'cPath='. $cPath . $category['categories_id']) .'</loc>' . PHP_EOL
             . '    <priority>0.5</priority>' . PHP_EOL
             . '    <lastmod>'. date('c') .'</lastmod>'. PHP_EOL
             . '    <changefreq>weekly</changefreq>'. PHP_EOL
             . '  </url>'. PHP_EOL;
    }
    while ($product=tep_db_fetch_array($products_query)) {
      $output .= '  <url>' . PHP_EOL
               . '    <loc>'. tep_href_link(FILENAME_PRODUCT_INFO, 'cPath='. $cPath . $category['categories_id'] .'&products_id='. $product['products_id']) .'</loc>'. PHP_EOL
               . '    <priority>0.5</priority>'. PHP_EOL
               . '    <lastmod>'. date('c', strtotime($product['products_last_modified'])) . '</lastmod>'. PHP_EOL
               . '    <changefreq>weekly</changefreq>'. PHP_EOL
               . '  </url>'. PHP_EOL;
    }
    TiM_sitemap_catalog($category['categories_id'], $cPath . $category['categories_id'] . '_');
  }
}

$output .= '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL
         . '<urlset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd" xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL
         . '  <url>' . PHP_EOL
         . '    <loc>'. tep_href_link(FILENAME_DEFAULT) .'</loc>' . PHP_EOL
         . '    <priority>0.4</priority>' . PHP_EOL
         . '    <lastmod>'. date('c') .'</lastmod>' . PHP_EOL
         . '    <changefreq>daily</changefreq>' . PHP_EOL
         . '  </url>' . PHP_EOL;
         
TiM_sitemap_catalog();

$output .= '</urlset>';

ob_end_clean();
header('Content-type: application/xml');
echo mb_convert_encoding($output ?? '', 'UTF-8', 'ISO-8859-1');
exit;

?>