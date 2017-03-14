<?php
ini_set('memory_limit', '-1');
ini_set('display_errors', 'On');
error_reporting(E_ALL);
// make sure browsers see this page as utf-8 encoded HTML
header('Content-Type: text/html; charset=utf-8');
header("Access-Control-Allow-Origin: *");

$limit = 10;
$query = isset($_REQUEST['q'])?$_REQUEST['q']:false;
$results = false;

$csv1 = file('mapCNNFile.csv');
$csv2 = file('mapUSATodayFile.csv');

foreach($csv1 as $line) 
{
    $line = str_getcsv($line);
    $arr[$line[0]] = trim($line[1]);
}

foreach($csv2 as $line) 
{
    $line = str_getcsv($line);
    $arr[$line[0]] = trim($line[1]);
}

if ($query)
{
	// The Apache Solr Client library should be on the include path
    // which is usually most easily accomplished by placing in the
    // same directory as this script ( . or current directory is a default
    // php include path entry in the php.ini)

    require_once('Apache/Solr/Service.php');
	require_once('SpellCorrector.php');
    $solr = new Apache_Solr_Service('localhost', 8983, '/solr/cscihwcore/');
	
	$queryArr = explode(" ",$query);
	$correctedWords = array();
	$corrected = false;
	
	foreach($queryArr as $q){
		array_push($correctedWords,SpellCorrector::correct($q));
	}
	
	$res = array_udiff($queryArr, $correctedWords, 'strcasecmp');
	if(empty($res)){
		$corrected = false;
	}else{
		$corrected = true;
	}
	
	// if magic quotes is enabled then stripslashes will be needed
	if (get_magic_quotes_gpc() == 1) 
	{
        $query = stripslashes($query);
    }
    $param = [];
    if (array_key_exists("pagerank", $_REQUEST)) {
        $param['sort'] ="pageRankFile desc";
    }
	try
	{
    $results = $solr->search($query, 0, $limit, $param);
	}
	catch(Exception $e)
	{
	  // in production you'd probably log or email this error to an admin
	  // and then show a special message to the user but for this example
	  // we're going to show the full exception
	  die("<html><head><title>SEARCH EXCEPTION</title><body><pre>{$e->__toString()}</pre></body></html>");
	}
}
?>
<html>
  <head>
    <title>PHP Solr Client</title>
	<link rel="stylesheet" href="http://code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css">
    <script src="http://code.jquery.com/jquery-1.11.0.min.js"></script>
    <script src="http://code.jquery.com/ui/1.11.4/jquery-ui.js"></script>
  </head>
  <body>
    <form accept-charset="utf-8" method="get">
        <label for="q">Search:</label>
        <input id="q" name="q" type="text" value="<?php echo htmlspecialchars($query,ENT_QUOTES,'utf-8'); ?>"/>
        <input type="submit"/>
        <br>
        <input type="checkbox" name="pagerank" value="aaa" <?php if(isset($_REQUEST['pagerank'])) echo "checked='checked'";?> />Use Page Rank<br>
        <br>
    </form>
<?php 

// display results
if ($results)
{
  $total = (int)$results->response->numFound;
  $start = min(1, $total);
  $end = min($limit, $total);
?>

<?php if ($corrected == true)
{
	$completeQuery = "";
	foreach($correctedWords as $values) {
		$completeQuery = $completeQuery.$values." ";
	}
	$completeQuery = trim($completeQuery);
?>
    <p>Did you mean: <a href="http://localhost/solr-php-client/searchBox.php?q=<?php echo $completeQuery; ?>"><?php echo $completeQuery; ?></a></p>
<?php
}
?>
	<div>Results <?php echo $start; ?> - <?php echo $end;?> of <?php echo $total; ?>:</div>
	<ol>
<?php
// iterate result documents
foreach($results->response->docs as $doc)
{ 
?>
 <li>
 <div style="border: 1px solid black; text-align: left">
<?php 
    $id = $doc->id;
	//Locate the document id
	$fName = basename($id);
	$fName = basename($fName, ".html");
	$fName = $fName.".txt";
	$snippet = "";
	$qArr = explode(" ",$query);
	$handle = fopen("snippets/".$fName, "r") or die("Unable to open file!");
	$flag = 0;
	$finalSnippet = "";
	
	while (($line = fgets($handle)) !== false) {
        foreach($qArr as $item){
			if (stripos($line, $item) !== false) {
				$index = stripos($line,$item);
				$flag = 1;
				if($index > 152){
					$start = $index - 30;
					$end = $index + min(strlen($line),30);
					
					$snippet = substr($line,$start,$end-$start);
					$snippet = $snippet."...";
					$snippet = str_ireplace($item, "<strong>".$item."</strong>", $snippet);
					$finalSnippet = $finalSnippet.$snippet;
					
				}else{
					$snippet = $line;
					$snippet = str_ireplace($item, "<strong>".$item."</strong>", $snippet);
					$finalSnippet = $finalSnippet.$snippet;
				}
			}		
		}
		
		if($flag == 1){
			break;
		}
		
    }
	fclose($handle);

	$url = $doc->og_url;
	$url = urldecode($url);
	
	if($url=="")
	{
        $url1 = $id;
		$lastIndex = strripos($url1, '/');
		
		$temp = substr($url1,$lastIndex+1);
		$temp = urldecode($temp); 
		if (array_key_exists($temp,$arr))
        {
            $finalurl =  $arr[$temp];
			$url=$finalurl;
        }
   
	}
		      
    $size = $doc->stream_size;
    $size = intval($size/1024)."kb";
	
	$author = $doc->author;
	if(empty($author))
	{
		$author = "N/A";
	}
	$title = $doc->title;
	
	if(is_array($title))
	{
		$title = $title[0];
	}
	
	if(empty($title))
	{
		$title = "N/A";
	}
	
	$description = $doc->description;
	
?>
		<div style="width:100%;height:100px;">
			<div><a href="<?php echo $url; ?>"><?php echo $title;?></a><br>
			<strong>URL:</strong><?php echo $url;?><br>
			<?php echo $finalSnippet;?></div>
		</div>
	</div>
	</li>
<?php
}
?>
 </ol>
<?php
}		
	
?>
    <script>
		var stopWords = "a,able,about,above,abst,accordance,according,accordingly,across,act,actually,added,adj,\
        affected,affecting,affects,after,afterwards,again,against,ah,all,almost,alone,along,already,also,although,\
        always,am,among,amongst,an,and,announce,another,any,anybody,anyhow,anymore,anyone,anything,anyway,anyways,\
        anywhere,apparently,approximately,are,aren,arent,arise,around,as,aside,ask,asking,at,auth,available,away,awfully,\
        b,back,be,became,because,become,becomes,becoming,been,before,beforehand,begin,beginning,beginnings,begins,behind,\
        being,believe,below,beside,besides,between,beyond,biol,both,brief,briefly,but,by,c,ca,came,can,cannot,can't,cause,causes,\
        certain,certainly,co,com,come,comes,contain,containing,contains,could,couldnt,d,date,did,didn't,different,do,does,doesn't,\
        doing,done,don't,down,downwards,due,during,e,each,ed,edu,effect,eg,eight,eighty,either,else,elsewhere,end,ending,enough,\
        especially,et,et-al,etc,even,ever,every,everybody,everyone,everything,everywhere,ex,except,f,far,few,ff,fifth,first,five,fix,\
        followed,following,follows,for,former,formerly,forth,found,four,from,further,furthermore,g,gave,get,gets,getting,give,given,gives,\
        giving,go,goes,gone,got,gotten,h,had,happens,hardly,has,hasn't,have,haven't,having,he,hed,hence,her,here,hereafter,hereby,herein,\
        heres,hereupon,hers,herself,hes,hi,hid,him,himself,his,hither,home,how,howbeit,however,hundred,i,id,ie,if,i'll,im,immediate,\
        immediately,importance,important,in,inc,indeed,index,information,instead,into,invention,inward,is,isn't,it,itd,it'll,its,itself,\
        i've,j,just,k,keep,keeps,kept,kg,km,know,known,knows,l,largely,last,lately,later,latter,latterly,least,less,lest,let,lets,like,\
        liked,likely,line,little,'ll,look,looking,looks,ltd,m,made,mainly,make,makes,many,may,maybe,me,mean,means,meantime,meanwhile,\
        merely,mg,might,million,miss,ml,more,moreover,most,mostly,mr,mrs,much,mug,must,my,myself,n,na,name,namely,nay,nd,near,nearly,\
        necessarily,necessary,need,needs,neither,never,nevertheless,new,next,nine,ninety,no,nobody,non,none,nonetheless,noone,nor,\
        normally,nos,not,noted,nothing,now,nowhere,o,obtain,obtained,obviously,of,off,often,oh,ok,okay,old,omitted,on,once,one,ones,\
        only,onto,or,ord,other,others,otherwise,ought,our,ours,ourselves,out,outside,over,overall,owing,own,p,page,pages,part,\
        particular,particularly,past,per,perhaps,placed,please,plus,poorly,possible,possibly,potentially,pp,predominantly,present,\
        previously,primarily,probably,promptly,proud,provides,put,q,que,quickly,quite,qv,r,ran,rather,rd,re,readily,really,recent,\
        recently,ref,refs,regarding,regardless,regards,related,relatively,research,respectively,resulted,resulting,results,right,run,s,\
        said,same,saw,say,saying,says,sec,section,see,seeing,seem,seemed,seeming,seems,seen,self,selves,sent,seven,several,shall,she,shed,\
        she'll,shes,should,shouldn't,show,showed,shown,showns,shows,significant,significantly,similar,similarly,since,six,slightly,so,\
        some,somebody,somehow,someone,somethan,something,sometime,sometimes,somewhat,somewhere,soon,sorry,specifically,specified,specify,\
        specifying,still,stop,strongly,sub,substantially,successfully,such,sufficiently,suggest,sup,sure,t,take,taken,taking,tell,tends,\
        th,than,thank,thanks,thanx,that,that'll,thats,that've,the,their,theirs,them,themselves,then,thence,there,thereafter,thereby,\
        thered,therefore,therein,there'll,thereof,therere,theres,thereto,thereupon,there've,these,they,theyd,they'll,theyre,they've,\
        think,this,those,thou,though,thoughh,thousand,throug,through,throughout,thru,thus,til,tip,to,together,too,took,toward,towards,\
        tried,tries,truly,try,trying,ts,twice,two,u,un,under,unfortunately,unless,unlike,unlikely,until,unto,up,upon,ups,us,use,used,\
        useful,usefully,usefulness,uses,using,usually,v,value,various,'ve,very,via,viz,vol,vols,vs,w,want,wants,was,wasn't,way,we,wed,\
        welcome,we'll,went,were,weren't,we've,what,whatever,what'll,whats,when,whence,whenever,where,whereafter,whereas,whereby,wherein,\
        wheres,whereupon,wherever,whether,which,while,whim,whither,who,whod,whoever,whole,who'll,whom,whomever,whos,whose,why,widely,\
        willing,wish,with,within,without,won't,words,world,would,wouldn't,www,x,y,yes,yet,you,youd,you'll,your,youre,yours,yourself,\
        yourselves,you've,zero";
		
        $(function() {
			$("#q").autocomplete({
					minLength: 1,
					source : function(request, response) {
						var last = request.term.toLowerCase().trim().split(" ").pop(-1);
						var url_suggest = "http://localhost:8983/solr/cscihwcore/suggest?q=";
						var end = "&wt=json";
						
						var URL = url_suggest + last + end;
						
						$.ajax({
							url : URL,
							success : successHandler,
							dataType : 'jsonp',
							jsonp : 'json.wrf'
						});
					
						function successHandler(data,textStatus, jqXHR) {
							var last = $("#q").val().toLowerCase().trim().split(" ").pop(-1);
							var suggestions = data.suggest.suggest[last].suggestions;
												
							suggestions = $.map(suggestions, function (value, key) {
								var next = "";
								var query = $("#q").val();
								var queries = query.split(" ");
								
								if (queries.length > 1) {
									var lastIndex = query.lastIndexOf(" ");
									next = query.substring(0, lastIndex + 1).toLowerCase();
								}
								
								if (next == "" && isStopWord(value.term)) {
									return null;
								}
								
								if (!/^[0-9a-zA-Z]+$/.test(value.term)) {
									return null;
								}
								return next + value.term;
							});
							response(suggestions.slice(0, 10));
						}
					},
				});
        });
		
        function isStopWord(word)
        {
            var regex = new RegExp("\\b"+word+"\\b","i");
            return stopWords.search(regex) < 0 ? false : true;
        }
        
    </script>
</body>
</html>