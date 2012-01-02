#!/usr/local/bin/perl
# $Id: rfc2html.pl,v 1.15 2001/09/04 10:39:49 gerald Exp $
#
# OBSOLETE in favor of http://dev.w3.org/cvsweb/2001/rfc2html/
#
# Usage:
#  perl rfc2html.pl rfc2616.txt >rfc2616.html
#   also creates rfc2616-secN.html for each section N
#
# Features
#  splits by section
#  marks up TOC with hypertext links
#    using rel=Section per HTML 4.0
#  marks up section headings with anchors
#  marks up indented sections as <pre>
#  marks up paragraphs and definition lists
#  marks up cross references and bibliographic references
#   using rel=xref, rel=bibref
#   (some false matches)
#  marks up references section with links to other RFCs and docs
#  creates well-formed XML output
#
#
# TODO
#  markup ul, ol in body text as such rather than as <pre>
#  generalize &convert() params: title, short title, basename, bibsection
#     for other RFCs
#
# BY
#  Dan Connolly <connolly@w3.org>
#  http://www.w3.org/People/Connolly/
#  
#  with thanks to Pete Whiting for a fix on 19 Jan 2000
#
# LICENSE
#
# Copyright (c) 1999 World Wide Web Consortium (W3C, http://www.w3.org/),
# (Massachusetts Institute of Technology, Institut National de
# Recherche en Informatique et en Automatique, Keio University). All
# Rights Reserved. 
#
# Permission to use, copy, modify, and distribute this software
# and its documentation for any purpose and without fee or
# royalty is hereby granted, per the terms and conditions in
#
# W3C Intellectual Property Notice and Legal Disclaimers
# http://www.w3.org/COPYRIGHT
# 1999/07/28 13:54:29

use strict;

my($xmlns) = 'http://www.w3.org/1999/xhtml';

&convert('Hypertext Transfer Protocol -- HTTP/1.1',
	 'HTTP/1.1', 'rfc2616', 17,
	 'Fielding, et al.',
	 'RFC 2616');

sub convert{
  my($title, $stitle, $base, $bibsec, $by, $docno) = @_;
  my($state);
  my($firstLine, $list, $toclevel, $citation);

  $state = 'start';

  print "<html xmlns='$xmlns'>\n";
  print "<head><title>$title</title></head><body>\n";

  while(<>){
#      warn "$state $_";
    s/&/&amp;/g;
    s/</&lt;/g;

    next if /^Fielding, et al./;
    next if /^RFC 2616/;

    print STDERR "$state: note: [[^$_]]\n" if (/^       (Note:)/);

    s/^(\d+)\. /$1 /; # copyright statement header is goofy

    if($state eq 'start'){
      if(/\S/){
	print "<pre>\n";
	$state = 'banner';
      }
    }
    elsif($state eq 'banner'){
      if(/^\s*$/){ # blank line
	$state = 'title';
	print "</pre>\n";
	print "<h1>\n";
      }else{
	print $_;
      }
    }
    elsif($state eq 'title'){
      if(/\S/){
	print $_;
	print "</h1>\n";
	$state = 'body';
      }
    }

    elsif($state eq 'body'){
      if(/^\s*$/){ # blank line
	# nothing
      }
      elsif(/^   ((\d+)(.\d+)?(.\d+)?)\s+([^\.]+)\.\./){
	my($num, $sec, $sub, $subsub, $heading) = ($1, $2, $3, $4, $5);
	die "bad toc $toclevel: $_" if ($sub || $subsub);

	print "<ol class='toc'>\n";
	print "<li><a rel='Section' href='$base-sec$sec.html#sec$num'>$heading</a> ... $num</li>\n";
	$state = 'toc';
	$toclevel = 1;
      }
      elsif(/^((\d+)((\.\d+)*))\s+([^]*)/){
	my($sec, $sub, $heading) = ($2, $3, $5);

	&changeSection($sec, $sub, $heading,
		       $title, $stitle, $base, $docno, $by);
      }
      elsif(/^\S/){
	print "<h2>\n";
	print $_;
	print "</h2>\n";
      }
      elsif(/^   (\[(\d+)\])\s+(.*)/){
	$citation = $_;
	print "<dl class='bib'>\n";
	$state = 'bib';
      }
      elsif(/^       (Note:)/){
	$_ = &addrefs($base, $bibsec, $_);

	print "<p><strong>$1</strong>$'";
	$list = undef;
	$state = 'note';
      }
      elsif(/^   \S/){
	$_ = &addrefs($base, $bibsec, $_);

	$firstLine = $_;
	$list = undef;
	$state = 'block';
      }
      elsif(/^    /){
	die "$state: unflushed $firstLine [[$firstLine]]" if $firstLine;

	print "<pre>$_";
	$state = 'pre';
      }
      else{
	die "$state: what? [[$_]]";
      }
    }

    elsif($state eq 'toc'){
      if(/^   ((\d+)(\.\d+)?(\.\d+)?)\s+([^\.]+)/){
	my($num, $sec, $sub, $subsub, $heading) = ($1, $2, $3, $4, $5);

	if($toclevel == 1){
	  if($sub){
	    print "<ol>\n";
	    $toclevel = 2;
	    if($subsub){
	      print "<ol>\n";
	      $toclevel = 3;
	    }
	  }
	}
	elsif($toclevel == 2){
	  if($sub){
	    if($subsub){
	      print "<ol>\n";
	      $toclevel = 3;
	    }
	  }
	  else{
	    print "</ol>\n";
	    $toclevel = 1;
	  }
	}elsif($toclevel == 3){
	  if($subsub){
	    # stay at level 3
	  }else{
	    print "</ol>\n";
	    $toclevel = 2;
	    if($sub){
	    }else{
	      print "</ol>\n";
	      $toclevel = 1;
	    }
	  }
	}

	print "<li><a rel='Section' href='$base-sec$sec.html#sec$num'>$heading</a> ... $num</li>\n";
      }
      elsif(/^\s*$/){ # blank line terminates TOC;
	              # hmm... watch out for page breaks!
	if($toclevel == 1){
	  print "</ol>\n";
	  &colophon();
	  $state = 'body';
	}else{
	  warn "blank line in toc level $toclevel";
	}
      }
      else{
	die "$state: what? [[$_]]";
      }
    }


    elsif($state eq 'block'){
      if(/^((\d+)((\.\d+)*))\s+([^]*)/){
	my($sec, $sub, $heading) = ($2, $3, $5);
	
	print "</dl>\n";
	&changeSection($sec, $sub, $heading,
		       $title, $stitle, $base, $docno, $by);
	$state = 'body';
      }
      elsif(/^\S/){
	print "</dl>\n";
	print "<h2>$_</h2>\n";
	$state = 'body';
      }
      elsif(/^   \S/){
#          warn "hit this $_";
	if($firstLine){
	  print "</$list>\n" if $list;
	  
	  print "<p>\n";
	  print $firstLine;
	  $firstLine = undef;
	  print $_;
	  $state = 'p';
	}else{
	  $firstLine = $_;
	}
      }
      elsif(/^       (Note:)/){
	print "<p><strong>$1</strong>$'";
	$state = 'note';
      }
      elsif(/^       ?\S/){
	if($list ne 'dl'){
	  print "</$list>\n" if $list;
	  print "<dl>\n";
	}
	
	print " <dt>$firstLine</dt>";
	$firstLine = undef;
	print " <dd>$_";
	$state = 'dd';
      }
      elsif(/^\s*$/){  # added the $ - don't want to drop a line with text 
 	if($firstLine){
	  print "<p>\n";
	  print $firstLine;
	  $firstLine = undef;
	  print "</p>\n";
	  $state = 'body';
	}
      }
      elsif(/^\s*\S/){
# this missed all of the above, but it looks like valid text,  so
# lets just use it like it was a normal paragraph.  I wanted to keep
# this separate from the three space rule, but it has the exact same
# behavior.
        if($firstLine){
          print "</$list>\n" if $list;
              
          print "<p>\n";
          print $firstLine;
          $firstLine = undef;
          print $_;
          $state = 'p';
        }else{
          $firstLine = $_;
        }
      }
      else{
        die "$state: what? [[$_]]\n firstline: [[$firstLine]] list: [[$list]]";
      }
    }

    elsif($state eq 'p'){
      $_ = &addrefs($base, $bibsec, $_);

      if(/^ *\S/){ 
	print $_;
      }
      elsif(/^\s*$/){
	print "</p>\n";
	$state = 'body';
      }
      else{
	die "$state: what? [[$_]]";
      }
    }

    elsif($state eq 'note'){
      $_ = &addrefs($base, $bibsec, $_);

      if(/^       \S/){
	print $_;
      }
      elsif(/^\s*$/){
	print "</p>\n";
	$state = ($list ? 'block' : 'body');
      }
      else{
	die "$state: what? [[$_]]";
      }
    }

    elsif($state eq 'dd'){
      $_ = &addrefs($base, $bibsec, $_);

      if(/^       ?\S/){
	print $_;
      }
      elsif(/^         \S/){
	print STDERR "$state: pre: [[$_]]\n";
	print "<pre>$_</pre>";
      }
      elsif(/^\s*$/){
	$list = 'dl' unless $list;
	print "</dd>\n";
	$state = 'block';
      }
      else{
	die "$state: what? [[$_]]";
      }
    }

    elsif($state eq 'bib'){
      if(/^   (\[(\d+)\])\s+(.*)/){
        my($label, $num, $rest) = ($1, $2, $3);

	&cite($citation) if $citation;

	$citation = $_;
      }
      elsif(/^((\d+)((\.\d+)*))\s+([^]*)/){
	my($sec, $sub, $heading) = ($2, $3, $5);

	&cite($citation) if $citation;
	print "</dl>\n";

	&changeSection($sec, $sub, $heading,
		       $title, $stitle, $base, $docno, $by);
	$state = 'body';
      }
      else{
	$citation = $citation . $_;
      }
    }

    elsif($state eq 'pre'){
      $_ = &addrefs($base, $bibsec, $_);

      die "firstline: $_" if $firstLine;

      if(/^\s*$/){
	print "</pre>\n";
	$state = 'body';
      }else{
	print $_;
      }
    }

    else{
      die "unkown state $state";
    }
  }

  print "</body></html>\n";

}

sub addrefs{
  my($base, $bibsec, $l) = @_;

  $l =~ s, ((\d+)(\.\d+)+), <a rel='xref' href='$base-sec$2.html#sec$1'>$1</a>,g;
  $l =~ s,(\[(\d+)\]),<a rel='bibref' href='$base-sec$bibsec.html#bib$2'>$1</a>,g;

  return $l;
}

sub changeSection{
  my($sec, $sub, $heading,
    $title, $stitle, $base, $docno, $by) = @_;
  
  print STDERR "section: [[$sec]][[$sub]][[$heading]]\n";

  if($sub){
    print "<h3><a name='sec$sec$sub'>$sec$sub</a> $heading</h3>\n";
  }else{
    print "</body></html>\n";

    open(SECTION, ">$base-sec$sec.html");
    select(SECTION);

    print "<html xmlns='$xmlns'>\n";
    print "<head><title>$stitle: $heading</title></head>\n";

    print "<body><address><p>part of <a rev='Section' href='$base.html'>$title</a><br />\n";
    print "$docno $by</p></address>\n";
    print "<h2><a name='sec$sec$sub'>$sec$sub</a> $heading</h2>\n";
  }
}

sub cite{
  my($citation) = @_;
  local($_);
  $_ = $citation;

  my($num, $label, $by, $title, $addr);

  s/^\s*//;
  s/\s+/ /g;

  s/HTTPLat ency.html/HTTPLatency.html/; # URL split across lines

  if(s/^(\[(\d+)\])\s*//){
    ($num, $label) = ($2, $1);

    if(s/^([^\"]*)\"([^\"]+)\"//){
      ($by, $title) = ($1, $2);
    }

    if(/RFC (\d+)/){
      my($RFCAddrFormat) = "http://www.ietf.org/rfc/rfc%04d.txt";
      $addr = sprintf("$RFCAddrFormat", $1);
    }

    if(m;((ftp|http)://[^,> ]+);){
      $addr = $1;
      $addr =~ s/\.$//; # period at the end of a URL is probably punctuation
    }

    print "<dt><a name='bib$num'>$label</a></dt>\n";
    if($addr){
      print "<dd>$by <cite><a href='$addr'>$title</a></cite> $_</dd>\n";
    }else{
      print "<dd>$by <cite>$title</cite> $_</dd>\n";
    }
  }
}

sub colophon{
    print "<address>\n";
    print "<p>derived from <a rel='derived-from' href='rfc2616.txt'>RFC2616</a> by way of <br />\n";
    print "<a href='rfc2html.pl'>rfc2html.pl</a> ", '$Revision: 1.15 $ $Date: 2001/09/04 10:39:49 $', " by<br />\n";
    print "<a href='http://www.w3.org/People/Connolly/'>Dan Connolly</a></p>\n";
    print "</address>\n";
}

