<?php
    
include_once 'amazonProductDisplayV2.php';

$amazonAstoreCrawler = new AmazonProductDisplayV2("Honda Goldwing");
echo $amazonAstoreCrawler->getDisplayHtml();

    
?>