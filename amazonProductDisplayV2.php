<?php

include_once "phpQuery-onefile.php";

class AmazonProductDisplayV2{
    
    const ASTORE_BASE_URL = 'http://astore.amazon.com/hondamotorcycleaccessories01-20/search?node=6';
    const UTF8CHARSET = '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
    const AMAZON_QUICK_LINKER_SCRIPT = '<SCRIPT charset="utf-8" type="text/javascript" src="http://ws.amazon.com/widgets/q?ServiceVersion=20070822&MarketPlace=US&ID=V20070822/US/mangarelease-20/8005/e90b1a35-0741-4bdc-a67d-0077396d264a"> </SCRIPT> <NOSCRIPT><A HREF="http://ws.amazon.com/widgets/q?ServiceVersion=20070822&MarketPlace=US&ID=V20070822%2FUS%2Fmangarelease-20%2F8005%2Fe90b1a35-0741-4bdc-a67d-0077396d264a&Operation=NoScript">Amazon.com Widgets</A></NOSCRIPT>';
    const DEFAULT_KEYWORD = "honda";
    
    private $ListOfProduct = array();
    private $navigationHTML = "";
    private $errorMessage = "";
    private $keywords = "";
    
    public function __construct( $keywords){
        
        if( trim($keywords) == ""){
            $keywords = self::DEFAULT_KEYWORD;
        }
        
        $this->keywords = urlencode( $keywords );
        $this->getProductFromAStoreSearchResultPage();
    }
    
    private function getProductFromAStoreSearchResultPage(){
    
        $html = $this->getAStoreHTML();
        
        //if error occur, return error message
        if( strlen( $this->errorMessage ) != 0 ){
            return $this->errorMessage; 
        }
        
        $doc = phpQuery::newDocument($html);
        
        //insert amazon quick linker script
        echo self::AMAZON_QUICK_LINKER_SCRIPT;
        
        //insert Charset (override existing charset)
        echo self::UTF8CHARSET;
        
        $allProducts =  $doc->find('#searchResults'); 
        $links = $allProducts->find(".tdimage a, .tddescription a"); 
        
        //if no product found
        if( count( $links ) == 0  ){
            $this->errorMessage =  'No products matching your query have been found in our store. Please bookmark this page and come back soon to see if we have what you want.';
            return;
        }

        $this->replaceProductLinks( $links );
        
        //extract image, description and price and merge together as a single object
        
        $imageCols = $allProducts->find(".tdimage"); 
        $descriptionCols = $allProducts->find(".tddescription a");
        $tdDescription = $allProducts->find(".tddescription");
        
        foreach ( $tdDescription as $key=>$description ){
            $pieces = explode("<br>" , pq($description)->html() );
            $priceCols[$key] = preg_replace('/[^\$0-9\.]+/', '', $pieces[1]);
        }
        
        $this->ListOfProduct = $this->createListOfProduct( $imageCols , $descriptionCols , $priceCols);

        //display navigation
        $navigationLinks = $allProducts->find(".pagination a"); 
        $this->replaceNavigationLinks( $navigationLinks );
        $this->navigationHTML = $allProducts->find(".pagination td")->html();

    }
    
    private function getAStoreHTML(){
        try{
            $astoreURL  = $this->getAStoreURL();
            //echo $astoreURL . "<br />";
            $html = file_get_contents($astoreURL);
        }catch(Exception $e){
            $this->errorMessage =  'No products matching your query have been found in our store. Please bookmark this page and come back soon to see if we have what you want.';
            //echo '<span id="amazonError" style="display:hidden">' .  $e->getMessage() . '</span>';
            return;
        }
        
        return $html;
    }
    
    // purpose: create a list of product from DOM reference to image list, description list and price list
    // @parm
    // $imageList - DOM reference to imageList
    // $descriptionList - DOM reference to descriptionList
    // $priceList - DOM reference to priceList
    private function createListOfProduct( $imageList , $descriptionList , $priceList ){
    
        foreach ( $imageList as $key=>$image ){
            $currProduct = new clsProduct();
            $currProduct->image = pq($image);
            $productList[$key] = $currProduct;
        }
        
        foreach ( $descriptionList as $key=>$description ){
            $currProduct = $productList[ $key ];
            $originalDescription = pq($description)->html();
            $truncateDescription = substr( $originalDescription , 0 , 52 );
            
            if( strlen( $originalDescription ) > 53 ){
                $truncateDescription .= '...';
            }
            pq($description)->html( $truncateDescription );
            $currProduct->description  = pq($description);
        }

        foreach ( $priceList as $key=>$price ){
            $currProduct = $productList[ $key ];
            $currProduct->price = $price; 
        }
        
        return $productList;
    }
    

    // purpose: to replace astore links with amazon defined user attribute 
    // (note: amazon quick linker will then detect element with this attribute and replace them with affiliate links
    //        that link directly to amazon product page, instead of astore detail page.)
    // readmore: https://widgets.amazon.ca/Amazon-QuickLinker-Widget/
    //
    // @parm 
    // $links - reference to list of links in DOM   
    private function replaceProductLinks( $links ){
        
        foreach ( $links as $link ){
            $asinNumber = $this->extractASINNumber( pq($link)->Attr("href") );
            pq($link)->removeAttr("href");
            pq($link)->Attr( "type" , "amzn" );
            pq($link)->Attr( "asin" , $asinNumber );
        }
    }
    
    // purpose: to replace astore navigation links with current page links 
    // example: replace [astore url]?node=19&page=7 to [currentpage url]?page=7
    //
    // @parm 
    // $navigationLinks - reference to list of navigation links in DOM 
    private function replaceNavigationLinks( $navigationLinks ){
        
        $pageURI = explode("?" , $_SERVER['REQUEST_URI'] );
        $pageURI = $pageURI[0];

        foreach( $navigationLinks as $navigationLink ){
            $href = pq($navigationLink)->attr("href");
            
            $arrayParseURL = parse_url($href);
            parse_str( $arrayParseURL['query'] , $output );
            $pageNo  = $output['page'];
            
            $newHref = $pageURI . '?page=' . $pageNo;
            pq($navigationLink)->attr("href" , $newHref );
        }
    }

    
    public function getDisplayHtml(){
        
        if( strlen( $this->errorMessage ) != 0 ){
            return $this->errorMessage;
        }
        
        $output = '';
        $output .= '<style>#divProducts imgz{width:125px; height:99px;} #divProducts a{width:125px; position:relative;}</style>';
        $output .= "<div style='width:570px; overflow:auto;' id='divProducts'>";
        
        foreach ( $this->ListOfProduct as $product ){
            $output .= "<span style='width:250px; height:100px; float:left; padding-bottom:15px;' class='itemname'>";
                $output .= "<span style='width:100px; float:left; '>";
                    $output .= $product->image->html() ;
                $output .= "</span>";
                $output .= "<span style='width:150px; float:right; position:relative; left:0px;'>";
                    $output .= $product->description;                    
                    $output .= "<br />";
                    $output .= "<span class='price' style='position:relative; left:10px;'>";
                        $output .= $product->price;
                    $output .= "</span>";
                $output .= "</span>";
            $output .= "</span>";
        }
        $output .= "</div>";
        
        $output .= "<br />";
        $output .= "<div style='width:570px; ' class='price' >";
            $output .= $this->navigationHTML;
        $output .= "</div>";
        
        return $output;
    }
    
    
    // purpose: extract ASINNumber from url
    // link url example: /hondamotorcycleaccessories-20/detail/B001KPCUPC/178-6461355-7239444
    // @parm
    // $source - URL   
    private function extractASINNumber( $source ){
            
        $pieces = explode("/", $source);
        $ASINNumber = $pieces[ 3 ]; 
        
        return $ASINNumber;
    }
 
    
    private function getAStoreURL(){

        $page = 1;
        
        if( ! empty( $_GET['page'] ) ){
            $page =  $_GET['page'];
        }
        
        return self::ASTORE_BASE_URL . "&keywords=" . $this->keywords . "&page=" . $page ;
    }

}

class clsProduct{
    public $image;
    public $description;
    public $price;
    
    public function toString(){
        return $this->image . "-" . $this->description . "-" . $this->price;
    }
}




?>