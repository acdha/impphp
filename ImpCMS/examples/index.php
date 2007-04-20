<?
	/**
	 * $Id$
		 * $ProjectHeader: ImpCMS 0.3 Fri, 05 Apr 2002 23:34:31 -0800 chris $
		 *
		 **/

	function format_long_date($UnixDate)	{ return date("h:i a F jS, Y" , $UnixDate); }
	function format_short_date($UnixDate)	{ return date("h:iA m-j", $UnixDate); }
	function format_date($UnixDate)				{ return date("F j, Y ", $UnixDate); }

	require_once("ImpCMS/ImpCMS.php");

	if (empty($_REQUEST['ID'])) {
		$Document = $ImpCMS->getRootDocument() or trigger_error("Unable to load root document! Check your installation.", E_USER_ERROR);
	} else {
		$Document = $ImpCMS->getDocument($_REQUEST['ID']) or trigger_error("Unable to load document! Check your installation.", E_USER_ERROR);
	}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/2000/REC-xhtml1-20000126/DTD/xhtml1-strict.dtd">
<head>
		<title><?=$Document->Title?></title>
		<link rel="stylesheet" rev="stylesheet" href="demo.css">
</head>
<html>
	<body>
		<!-- Basic 3 column layout -->
		<table border="0" cellpadding="0" cellspacing="0" width="100%" height="100%">
			<tr>
				<td width="140" class="NavList">
					<div class="Header">Navigation</div>
					<div class="Contents">
						<?
							if ($Document->Parent) {
								print '<a class="ParentLink" href="' . $_SERVER['PHP_SELF'] . '?ID=' . $Document->Parent->ID . '">[Up] <b>' . $Document->Parent->Title . '</b></a>';
							}
						?>
						<?
							foreach($Document->getChildren() as $Child) {
						?>
								<a class="NavLink" href="<?=$_SERVER['PHP_SELF']?>?ID=<?=$Child->ID?>"><?=$Child->Title?></a>
						<?
							}
						?>
					</div>
				</td>
				<td class="Document">
					<div class="Title"><?=$Document->Title ?></div>
					<div class="InfoHeader"><?
						print format_short_date($Document->Created);
						if ($Document->Created != $Document->Modified) {
							print " (last updated " . format_short_date($Document->Modified) . ")";
						}
					?></div>

					<div class="Body"><?=$Document->Body?></div>

				</td>
				<td width="150" class="ResourceList">
					<div class="Header">Resources</div>
					<div class="Contents">
						<?
							foreach($Document->getResources() as $Resource) {
								print "<div>$Resource->Title<br><span style=\"font-size: xx-small\">$Resource->ContentWidth x $Resource->ContentHeight $Resource->MimeType</span></div>";
							}
						?>
					</div>
				</td>
			</tr>
		</table>
	</body>
</html>
