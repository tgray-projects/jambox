# htmlhelp assets

`build.properties` contains properties that inform folder/file locations,
and DocBook properties that configureformatting or transformation
behavior.

`htmlhelp.xsl` is an XSLT stylesheet used in HTML generation that will be
compiled into a CHM file with Microsoft's HTMLHelp Workshop. It also
provides an opportunity to customize any of the styles provided by the
master Perforce `htmlhelp.xsl` stylesheet, or to completely override it.

The `images` folder should contain any images referenced within the guide.
Ideally, images can be in any web readable formats, such as PNG, JPEG,
GIF, etc.

<!--- vim: set ts=2 sw=2 tw=74 ai si: -->
