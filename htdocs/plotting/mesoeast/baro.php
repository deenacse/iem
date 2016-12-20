<?php
include("../../../config/settings.inc.php");
//  1 minute data plotter 

$year = isset($_GET["year"]) ? $_GET["year"] : date("Y");
$month = isset($_GET["month"]) ? $_GET["month"] : date("m");
$day = isset($_GET["day"]) ? $_GET["day"] : date("d");


if (strlen($year) == 4 && strlen($month) > 0 && strlen($day) > 0 ){
  $myTime = strtotime($year."-".$month."-".$day);
} else {
  $myTime = strtotime(date("Y-m-d"));
}

$titleDate = strftime("%b %d, %Y", $myTime);
$formatFloor = mktime(0, 0, 0, 1, 1, 2016);
$dirRef = strftime("%Y/%m/%d", $myTime);
$fcontents = file("/mesonet/ARCHIVE/data/$dirRef/text/ot/ot0006.dat");

$parts = array();
$tmpf = array();
//[DMF]$dwpf = array();
//[DMF]$sr = array();
$xlabel = array();

$start = intval( $myTime );
$i = 0;

$dups = 0;
$missing = 0;
$min_yaxis = 11000;
$min_yaxis_i = 110;
$max_yaxis = 0;
$max_yaxis_i = 0;
$prev_Tmpf = 0.0;

while (list ($line_num, $line) = each ($fcontents)) {

	$parts = split (" ", $line);
	if ($myTime < $formatFloor){
		$month = $parts[0];
		$day = $parts[1];
		$year = $parts[2];
		$hour = $parts[3];
		$min = $parts[4];
		$timestamp = mktime($hour,$min,0,$month,$day,$year);
	} else {
		$timestamp = strtotime(sprintf("%s %s %s %s", $parts[0], $parts[1],
				$parts[2], $parts[3]));
	}
  $thisTmpf = $parts[13];
  $thisTmpf = round((floatval($thisTmpf) * 33.8639),2);
//  if ($thisTmpf < -50 || $thisTmpf > 150 ){
//  } else {
  if ($max_yaxis < $thisTmpf){
    $max_yaxis = ceil($thisTmpf);
  }
  if ($min_yaxis > $thisTmpf){
    $min_yaxis = floor($thisTmpf);
  }

    $tmpf[$i] = $thisTmpf;
    $i++;


} // End of while

$xpre = array(0 => '12 AM', '1 AM', '2 AM', '3 AM', '4 AM', '5 AM',
        '6 AM', '7 AM', '8 AM', '9 AM', '10 AM', '11 AM', 'Noon',
        '1 PM', '2 PM', '3 PM', '4 PM', '5 PM', '6 PM', '7 PM',
        '8 PM', '9 PM', '10 PM', '11 PM', '12 AM');


for ($j=0; $j<25; $j++){
  $xlabel[$j*60] = $xpre[$j];
}



include ("$rootpath/include/jpgraph/jpgraph.php");
include ("$rootpath/include/jpgraph/jpgraph_line.php");

// Create the graph. These two calls are always required
$graph = new Graph(600,300,"example1");
$graph->SetScale("textlin", $min_yaxis - 1.0, $max_yaxis + 1.0);
$graph->img->SetMargin(65,40,45,60);
//$graph->xaxis->SetFont(FONT1,FS_BOLD);
$graph->xaxis->SetTickLabels($xlabel);
//$graph->xaxis->SetTextLabelInterval(60);
$graph->xaxis->SetTextTickInterval(60);

$graph->xaxis->SetLabelAngle(90);
$graph->yaxis->scale->ticks->Set(1,0.5);
$graph->yaxis->scale->SetGrace(10);
$graph->title->Set("Pressure");
$graph->subtitle->Set($titleDate );

$graph->legend->SetLayout(LEGEND_HOR);
$graph->legend->Pos(0.01,0.075);

//[DMF]$graph->y2axis->scale->ticks->Set(100,25);

$graph->title->SetFont(FF_FONT1,FS_BOLD,14);
$graph->yaxis->SetTitle("Pressure [mb]");

//[DMF]$graph->y2axis->SetTitle("Solar Radiation [W m**-2]");

$graph->yaxis->title->SetFont(FF_FONT1,FS_BOLD,12);
$graph->xaxis->SetTitle("Valid Local Time");
$graph->xaxis->SetTitleMargin(30);
//$graph->yaxis->SetTitleMargin(48);
$graph->yaxis->SetTitleMargin(45);
$graph->xaxis->title->SetFont(FF_FONT1,FS_BOLD,12);
$graph->xaxis->SetPos("min");

// Create the linear plot
$lineplot=new LinePlot($tmpf);
$lineplot->SetLegend("Pressure");
$lineplot->SetColor("red");

// Create the linear plot
//[DMF]$lineplot2=new LinePlot($dwpf);
//[DMF]$lineplot2->SetLegend("Dew Point");
//[DMF]$lineplot2->SetColor("blue");

// Create the linear plot
//[DMF]$lineplot3=new LinePlot($sr);
//[DMF]$lineplot3->SetLegend("Solar Rad");
//[DMF]$lineplot3->SetColor("black");

// Box for error notations
//[DMF]$t1 = new Text("Dups: ".$dups ." Missing: ".$missing );
//[DMF]$t1->SetPos(0.4,0.95);
//[DMF]$t1->SetOrientation("h");
//[DMF]$t1->SetFont(FF_FONT1,FS_BOLD);
//$t1->SetBox("white","black",true);
//[DMF]$t1->SetColor("black");
//[DMF]$graph->AddText($t1);

//[DMF]$graph->Add($lineplot2);
$graph->Add($lineplot);
//[DMF]$graph->AddY2($lineplot3);

$graph->Stroke();

?>