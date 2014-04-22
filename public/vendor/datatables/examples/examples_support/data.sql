CREATE TABLE IF NOT EXISTS `ajax` (
  `id` int(10) NOT NULL auto_increment,
  `engine` varchar(255) NOT NULL default '',
  `browser` varchar(255) NOT NULL default '',
  `platform` varchar(255) NOT NULL default '',
  `version` float NOT NULL default '0',
  `grade` varchar(20) NOT NULL default '',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Trident', 'Internet Explorer 4.0', 'Win 95+', '4', 'X' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Trident', 'Internet Explorer 5.0', 'Win 95+', '5', 'C' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Trident', 'Internet Explorer 5.5', 'Win 95+', '5.5', 'A' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Trident', 'Internet Explorer 6', 'Win 98+', '6', 'A' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Trident', 'Internet Explorer 7', 'Win XP SP2+', '7', 'A' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Trident', 'AOL browser (AOL desktop)', 'Win XP', '6', 'A' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Gecko', 'Firefox 1.0', 'Win 98+ / OSX.2+', '1.7', 'A' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Gecko', 'Firefox 1.5', 'Win 98+ / OSX.2+', '1.8', 'A' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Gecko', 'Firefox 2.0', 'Win 98+ / OSX.2+', '1.8', 'A' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Gecko', 'Firefox 3.0', 'Win 2k+ / OSX.3+', '1.9', 'A' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Gecko', 'Camino 1.0', 'OSX.2+', '1.8', 'A' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Gecko', 'Camino 1.5', 'OSX.3+', '1.8', 'A' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Gecko', 'Netscape 7.2', 'Win 95+ / Mac OS 8.6-9.2', '1.7', 'A' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Gecko', 'Netscape Browser 8', 'Win 98SE+', '1.7', 'A' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Gecko', 'Netscape Navigator 9', 'Win 98+ / OSX.2+', '1.8', 'A' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Gecko', 'Mozilla 1.0', 'Win 95+ / OSX.1+', '1', 'A' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Gecko', 'Mozilla 1.1', 'Win 95+ / OSX.1+', '1.1', 'A' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Gecko', 'Mozilla 1.2', 'Win 95+ / OSX.1+', '1.2', 'A' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Gecko', 'Mozilla 1.3', 'Win 95+ / OSX.1+', '1.3', 'A' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Gecko', 'Mozilla 1.4', 'Win 95+ / OSX.1+', '1.4', 'A' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Gecko', 'Mozilla 1.5', 'Win 95+ / OSX.1+', '1.5', 'A' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Gecko', 'Mozilla 1.6', 'Win 95+ / OSX.1+', '1.6', 'A' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Gecko', 'Mozilla 1.7', 'Win 98+ / OSX.1+', '1.7', 'A' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Gecko', 'Mozilla 1.8', 'Win 98+ / OSX.1+', '1.8', 'A' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Gecko', 'Seamonkey 1.1', 'Win 98+ / OSX.2+', '1.8', 'A' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Gecko', 'Epiphany 2.20', 'Gnome', '1.8', 'A' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Webkit', 'Safari 1.2', 'OSX.3', '125.5', 'A' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Webkit', 'Safari 1.3', 'OSX.3', '312.8', 'A' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Webkit', 'Safari 2.0', 'OSX.4+', '419.3', 'A' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Webkit', 'Safari 3.0', 'OSX.4+', '522.1', 'A' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Webkit', 'OmniWeb 5.5', 'OSX.4+', '420', 'A' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Webkit', 'iPod Touch / iPhone', 'iPod', '420.1', 'A' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Webkit', 'S60', 'S60', '413', 'A' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Presto', 'Opera 7.0', 'Win 95+ / OSX.1+', '-', 'A' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Presto', 'Opera 7.5', 'Win 95+ / OSX.2+', '-', 'A' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Presto', 'Opera 8.0', 'Win 95+ / OSX.2+', '-', 'A' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Presto', 'Opera 8.5', 'Win 95+ / OSX.2+', '-', 'A' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Presto', 'Opera 9.0', 'Win 95+ / OSX.3+', '-', 'A' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Presto', 'Opera 9.2', 'Win 88+ / OSX.3+', '-', 'A' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Presto', 'Opera 9.5', 'Win 88+ / OSX.3+', '-', 'A' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Presto', 'Opera for Wii', 'Wii', '-', 'A' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Presto', 'Nokia N800', 'N800', '-', 'A' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Presto', 'Nintendo DS browser', 'Nintendo DS', '8.5', 'C/A<sup>1</sup>' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'KHTML', 'Konqureror 3.1', 'KDE 3.1', '3.1', 'C' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'KHTML', 'Konqureror 3.3', 'KDE 3.3', '3.3', 'A' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'KHTML', 'Konqureror 3.5', 'KDE 3.5', '3.5', 'A' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Tasman', 'Internet Explorer 4.5', 'Mac OS 8-9', '-', 'X' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Tasman', 'Internet Explorer 5.1', 'Mac OS 7.6-9', '1', 'C' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Tasman', 'Internet Explorer 5.2', 'Mac OS 8-X', '1', 'C' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Misc', 'NetFront 3.1', 'Embedded devices', '-', 'C' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Misc', 'NetFront 3.4', 'Embedded devices', '-', 'A' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Misc', 'Dillo 0.8', 'Embedded devices', '-', 'X' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Misc', 'Links', 'Text only', '-', 'X' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Misc', 'Lynx', 'Text only', '-', 'X' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Misc', 'IE Mobile', 'Windows Mobile 6', '-', 'C' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Misc', 'PSP browser', 'PSP', '-', 'C' );
INSERT INTO ajax ( engine, browser, platform, version, grade ) VALUES ( 'Other browsers', 'All others', '-', '-', 'U' );