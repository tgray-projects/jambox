# PDF assets

`build.properties` contains properties that inform folder/file locations,
and DocBook properties that configureformatting or transformation
behavior.

`pdf.xsl` is an XSLT stylesheet used in PDF generation that provides an
opportunity to customize any of the styles provided by the master Perforce
`pdf.xsl` stylesheet, or to completely override it.

The `images` folder should contain any images referenced within the guide.
Ideally, images should be in SVG format as PDF is a resolution-independent
format. Screenshots can be in PNG, JPEG, or GIF format.

## Philosophy

Between the `build.properties` and `pdf.xsl` files, you can (almost)
completely change the transformation behavior. You can specify where
output is created, the PDF filename, where assets are copied from, which
DocBook XSL is used, and most DocBook properties.

The one aspect you cannot change is that PDF generation involves calling
Apache FOP; you won't be able to successfully configure EPUB generation
with the `pdf` build target.

<!--- vim: set ts=2 sw=2 tw=74 ai si: -->
