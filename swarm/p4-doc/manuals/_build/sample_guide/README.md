# Sample Guide

This folder demonstrates the expected structure and configuration
files for a guide that can be transformed into HTML or PDF by the
[doc_build](../README.md) infrastructure.

| File/Folder      | Description |
| ---------------- | ----------- |
| adoc/            | Folder containing AsciiDoc source for the guide. |
| assets           | Folder containing assets used in guide transformation. |
| build.properties | Ant property file, specifying where the guide source lives, and where the doc_build infrastructure exists. |
| build.xml        | Ant build file. |
| xml/             | Folder containing DocBook XML source for the guide. |

Note: You normally only need one source for a guide, AsciiDoc or Docbook.
AsciiDoc guides are transformed into DocBook with AsciiDoctor, and then
processed as a DocBook guide. This sample guide provides both formats so
you can test building HTML and PDF with either source format, and compare
markup strategies.

<!--- vim: set ts=2 sw=2 tw=74 ai si: -->
