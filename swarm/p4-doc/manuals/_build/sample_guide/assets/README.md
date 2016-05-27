# Assets

This folder contains assets that are used to construct a guide in one
of the available output formats. Format-specific assets should be placed
in separate folders, where the folder name matches the Ant output target.
Currently, the only supported output targets are:

* htmlhelp; used in the process of generating [CHM](#chm) files
* pdf: used to generate a PDF
* product: used to generate HTML that appears within a product
* publicsite: used to generate HTML that should appear on perforce.com

Each output format's `build.properties` file can specify an asset path
where format-specific assets should be copied from, as well as an
_extra_ assets path. This is useful if you have assets that appear in
multiple formats and you'd like to maintain only a single copy.

To use this common-asset facility, this, you would:

* Create a folder named something like `_common`, and populate it with
  whatever common assets are requiredr.

* Update the `build.properties` file in each output target's folder that
  needs to use the common assets, and configure the properties
  `assets-basedir-extra` and `assets-dirs-extra` appropriately.

<a name="chm"></a>
## CHM files

The doc infrastructure is not capable of generating a CHM file directly. It
produces the HTML files that Microsoft's HTMLHelp Workshop can compile into
a CHM file. The HTMLHelp Workshop is a free download:

http://www.microsoft.com/en-ca/download/details.aspx?id=21138
