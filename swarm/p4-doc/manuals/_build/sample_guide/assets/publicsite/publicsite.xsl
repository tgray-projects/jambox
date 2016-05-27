<?xml version="1.0" encoding="UTF-8"?>
<!-- vim: set ts=2 sw=2 tw=80 ai si: -->
<xsl:stylesheet
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
  xmlns:d="http://docbook.org/ns/docbook"
  xmlns:exsl="http://exslt.org/common"
  exclude-result-prefixes="d xsl exsl"
  version="1.0">

  <!-- ============================================================= -->
  <!-- Import Perforce styles                                        -->
  <!-- ============================================================= -->

  <xsl:import href="@p4-assets-dir@/publicsite.xsl"/>

  <!-- ============================================================= -->
  <!-- parameters and styles                                         -->
  <!-- ============================================================= -->

  <!-- disable Perforce analytics -->
  <xsl:param name="perforce.analytics">0</xsl:param>

  <xsl:param name="default.encoding">UTF-8</xsl:param>
  <xsl:param name="chunker.output.encoding">UTF-8</xsl:param>
  <xsl:param name="saxon.character.representation">native</xsl:param>

  <!-- uncomment the following lines to include a guide-specific CSS
       file within the generated HTML -->
  <!-- xsl:template name="user.webhelp.head.content">
    <link rel="stylesheet" type="text/css" href="css/guide.css"/>
  </xsl:template -->

</xsl:stylesheet>
