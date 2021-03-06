<?php
/*©lgpl*************************************************************************
*                                                                              *
* This file is part of FRIEND UNIFYING PLATFORM.                               *
*                                                                              *
* This program is free software: you can redistribute it and/or modify         *
* it under the terms of the GNU Lesser General Public License as published by  *
* the Free Software Foundation, either version 3 of the License, or            *
* (at your option) any later version.                                          *
*                                                                              *
* This program is distributed in the hope that it will be useful,              *
* but WITHOUT ANY WARRANTY; without even the implied warranty of               *
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the                 *
* GNU Affero General Public License for more details.                          *
*                                                                              *
* You should have received a copy of the GNU Lesser General Public License     *
* along with this program.  If not, see <http://www.gnu.org/licenses/>.        *
*                                                                              *
*****************************************************************************©*/

global $User, $SqlDatabase, $Logger;

// 1. Check if we're looking for software.......................................
$len = strlen('System:Software/');
$subpath = $subpathJoin = false;

if( isset( $args->args ) && substr( $args->args->path, 0, $len ) == 'System:Software/' )
{
	$subpath = substr( $args->args->path, $len, strlen( $args->args->path ) - $len );
	if( substr( $subpath, -1, 1 ) == '/' )
		$subpath = substr( $subpath, 0, strlen( $subpath ) - 1 );
	$subpath = explode( '/', $subpath );
	$subpathJoin = implode( '/', $subpath );
	// Ok, go fetch!

	// We need subpath here.. for now..
	if( !$subpath ) die( 'fail' );

	// Now try!
	$groups = $SqlDatabase->FetchObjects( '
		SELECT 
			ug.Name FROM FUserGroup ug, FUserToGroup utg 
		WHERE 
			utg.UserID=\'' . $User->ID . '\' AND utg.UserGroupID = ug.ID
	' );
	if( !$groups ) die( 'fail<!--separate-->User with no group can not use apps.' );
	
	$searchGroups = array(); foreach( $groups as $g ) $searchGroups[] = $g->Name;
	
	// Get all installed apps
	$instApps = $SqlDatabase->FetchObjects( '
		SELECT * FROM FApplication WHERE UserID=\'' . $User->ID . '\' 
	' );
	$appsByName = [];
	foreach( $instApps as $app )
	{
		$appsByName[$app->Name] = $app;
	}
	unset( $instApps );
	
	$out = [];
	
	foreach( array( 'resources/webclient/apps/', 'repository/' ) as $path )
	{
		if( file_exists( $path ) && is_dir( $path ) && $dir = opendir( $path ) )
		{
			$cats = [];
			$apps = [];
			
			while( $file = readdir( $dir ) )
			{
				if( $file{0} == '.' ) continue;
				if( !file_exists( $fz = ( $path . $file . '/Config.conf' ) ) )
					continue;
			
				// Skip non installed apps
				if( !isset( $appsByName[ $file ] ) ) continue;
			
				// For repositories
				if( file_exists( $path . $file . '/Signature.sig' ) )
				{
					if( !( $d = file_get_contents( 'repository/' . $file . '/Signature.sig' ) ) )
						continue;
					if( !( $js = json_decode( $d ) ) )
						continue;
					if( !isset( $js->validated ) )
						continue;
				}
				if( $f = file_get_contents( $fz ) )
				{
					if( $fj = json_decode( $f ) )
					{
						$joi = $fj->Category;
						if( substr( $joi, -1, 1 ) == '/' )
							$joi = substr( $joi, 0, strlen( $joi ) - 1 );
						// Is it subpath?
						if( substr( $joi, 0, strlen( $subpathJoin ) ) == $subpathJoin && strlen( $joi ) > strlen( $subpathJoin ) )
						{
							if( !in_array( $fj->Category, $cats ) )
							{
								$cats[] = $fj->Category;
							}
						}
						// Else, we got it!
						else if( $joi == $subpathJoin )
						{
							$fo = new stdClass();
							$fo->Filename = $file;
							$fo->Cat = $fj->Category;
							$apps[] = $fo;
						}
					}
				}
			}
			closedir( $dir );
	
			if( count( $cats ) )
			{
				foreach( $cats as $cat )
				{
					$cate = explode( '/', $cat );
					foreach( $cate as $k=>$v )
						$cate[$k] = ucfirst( $v );
				
					$o = new stdClass();
					$o->Filename = $cate[count($cate)-1];
					$o->Type = 'Directory';
					$o->MetaType = 'Directory';
					$o->IconClass = 'DirectoryBrown';
					$o->Path = 'System:Software/' . implode( '/', $cate ) . '/';
					$o->Permissions = '';
					$o->DateModified = date( 'Y-m-d H:i:s' );
					$o->DateCreated = '1970-01-01 00:00:00';
					$out[] = $o;
				}
			}
			if( count( $apps ) )
			{
				foreach( $apps as $app )
				{
					// Only allowed apps
					$r = AuthenticateApplication( $app->Filename, $User->ID, $searchGroups );
					if( $r && substr( $r, 0, 4 ) == 'fail' ) continue;
				
					$o = new stdClass();
					$o->Filename = $app->Filename;
					$o->Type = 'Executable';
					$o->MetaType = 'File';
					if( file_exists( $path . $app->Filename . '/icon.png' ) )
					{
						if( $path == 'repository/' )
						{
							$o->IconFile = '/system.library/module/?sessionid=' . $User->SessionID . '&module=system&command=repoappimage&i=' . $app->Filename;
						}
						else
						{
							$o->IconFile = '/webclient/apps/' . $app->Filename . '/icon.png';
						}
					}
					$o->Path = 'System:Software/' . $app->Cat . '/';
					$o->Permissions = '';
					$o->DateModified = date( 'Y-m-d H:i:s' );
					$o->DateCreated = '1970-01-01 00:00:00';
					$out[] = $o;
				}
			}
		}
	}
	if( count( $out ) > 0 )
		die( 'ok<!--separate-->' . json_encode( $out ) );
}
// Repositories
else if( isset( $args->args ) && substr( $args->args->path, 0, strlen( 'System:Repositories/FriendUP/' ) ) == 'System:Repositories/FriendUP/' )
{
	if( $dr = opendir( 'repository' ) )
	{
		$out = [];
		while( $file = readdir( $dr ) )
		{
			if( $file{0} == '.' ) continue;
			if( is_dir( 'repository/' . $file ) )
			{
				if( file_exists( 'repository/' . $file . '/package.zip' ) )
				{
					if( file_exists( 'repository/' . $file . '/Signature.sig' ) )
					{
						if( $d = file_get_contents( 'repository/' . $file . '/Signature.sig' ) )
						{
							if( $js = json_decode( $d ) )
							{
								if( isset( $js->validated ) )
								{
									$o = new stdClass();
									$o->Filename = $file . '.fpkg';
									$o->Path = 'System:Repositories/FriendUP/' . $o->Filename;
									$o->MetaType = 'File';
									$o->Type = 'File';
									$o->Filesize = filesize( 'repository/' . $file . '/package.zip' );
									// TODO: Get date!
									$o->DateModified = date( 'Y-m-d H:i:s' );
									$o->DateCreated = $o->DateModified;
									$out[] = $o;
								}
							}
						}
					}
				}
			}
		}
		closedir( $dr );
		die( 'ok<!--separate-->' . json_encode( $out ) );
	}
}
// DOS Drivers
else if( isset( $args->args ) && strtolower( trim( $args->args->path ) ) == 'system:devices/dosdrivers/' )
{
	if( $dr = opendir( 'devices/DOSDrivers' ) )
	{
		$out = [];
		while( $f = readdir( $dr ) )
		{
			if( $f{0} == '.' ) continue;
			$o = new stdClass();
			$o->Filename = $f;
			$o->Type = 'File';
			$o->MetaType = 'File';
			$o->IconClass = 'DOSDriver';
			$o->Path = 'System:Devices/DOSDrivers/' . $o->Filename;
			$o->Permissions = '';
			$o->DateModified = date( 'Y-m-d H:i:s' );
			$o->DateCreated = '1970-01-01 00:00:00';
			$out[] = $o;
		}
		closedir( $dr );
		
		if( count( $out ) )
		{
			die( 'ok<!--separate-->' . json_encode( $out ) );
		}
		die( 'fail' );
		
	}
}
// Sessions
else if( isset( $args->args ) && strtolower( trim( $args->args->path ) ) == 'system:devices/sessions/' )
{
	if( $rows = $SqlDatabase->FetchObjects( 'SELECT * FROM FUserSession WHERE UserID=\'' . $User->ID . '\' ORDER BY DeviceIdentity ASC' ) )
	{
		$out = [];
		foreach( $rows as $row )
		{
			if( $f{0} == '.' ) continue;
			$o = new stdClass();
			$o->Filename = $row->DeviceIdentity;
			$o->Type = 'File';
			$o->MetaType = 'File';
			$o->IconClass = 'DeviceSession';
			$o->Path = 'System:Devices/Sessions/' . $o->DeviceIdentity;
			$o->Permissions = '';
			$o->DateModified = date( 'Y-m-d H:i:s', $o->LoggedTime );
			$o->DateCreated = $o->DateModified;
			$out[] = $o;
		}
		if( count( $out ) )
		{
			die( 'ok<!--separate-->' . json_encode( $out ) );
		}
	}
	die( 'fail' );
}
// Cores
else if( isset( $args->args ) && strtolower( trim( $args->args->path ) ) == 'system:devices/cores/' )
{
	// TODO: Support other cores (friend core to friend core connection)
	$o = new stdClass();
	$o->Filename = 'Root';
	$o->Type = 'File';
	$o->MetaType = 'File';
	$o->IconClass = 'FriendCore';
	$o->Path = 'System:Devices/Cores/' . $o->Filename;
	$o->Permissions = '';
	$o->DateModified = date( 'Y-m-d H:i:s' );
	$o->DateCreated = '1970-01-01 00:00:00';
	$out[] = $o;
	die( 'ok<!--separate-->' . json_encode( $out ) );
}
// Subs
else if( isset( $args->path ) && strtolower( substr( $args->path, 0, strlen( 'system:devices/dosdrivers/' ) ) ) == 'system:devices/dosdrivers/' )
{
	$extra = preg_replace( '/system\:devices\/dosdrivers\//i', '', $args->path );
	if( isset( $extra ) )
	{
		if( file_exists( $f = ( 'devices/DOSDrivers/' . $extra . '/info.html' ) ) )
		{
			$info = file_get_contents( $f );
			die( '<!DOCTYPE html><html><head><link rel="stylesheet" href="/webclient/theme/theme_compiled.css"/></head><body style="overflow: auto; background: gray"><div class="Padding BorderRight BackgroundDefault"><p><strong>' . $extra . ', DOS driver</strong></p>' . $info . '</div></body></html>' );
		}
	}
	die( '<!DOCTYPE html><html><head><link rel="stylesheet" href="/webclient/theme/theme_compiled.css"/></head><body style="overflow: auto; background: gray"><div class="Padding BorderRight BackgroundDefault"><p><strong>DOS driver information</strong></p><p>DOS driver for FriendUP.</p><p>No information available.</p></div></body></html>' );
}
// Unimplemented
else if( isset( $args->args) && strtolower( trim( $args->args->path ) ) == 'system:devices/printers/' )
{
	die( 'fail<!--separate-->' );
}

die( 'fail<!--separate-->{"response":0,"message":"Unknown system path."}' );


?>
