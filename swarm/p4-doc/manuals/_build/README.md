# doc_build

This is the documentation build infrastructure that Perforce uses to
produces its own guides. It accepts both DocBook 5 and AsciiDoc source,
and can produce HTML and PDF output. Transformation is managed with Apache
Ant.

## Requirements

* Apache Ant, 1.8+
* Python 2.7+

If your guide is authored in AsciiDoc:
* Ruby 1.9+
* AsciiDoctor 1.5+

All of the other requirements are included.

## Building Output

Building the HTML or PDF for a guide is easy. First, `cd` into the guide's
root directory (using an appropriate [directory structure](sample_guide/README.md)).

Then, to transform the guide into HTML, invoke:

    $ ant publicsite

The resulting HTML is placed in a folder called `publicsite-generated`.

Note: Each time you invoke `ant publicsite`, the contents of `publicsite-generated`
are deleted, so don't commit any of those files. The resulting HTML should look
similar to the guides publishing on perforce.com.

To transform the guide into PDF, invoke:

    $ ant pdf
    
The resulting PDF is placed in a folder called `pdf-generated`. Each time you
invoke `ant pdf`, the contents of `pdf-generated` are deleted, so don't commit
any of those files. The resulting PDF should look similar to the guides
published on perforce.com, although the overall look depends on whether your
system has the fonts installed that Perforce uses for its guides.

The transformation process is fairly verbose, especially for PDF generation,
and often contains warnings that do not indicate a failed transformation.

<a name="manifest"></a>
## Manifest

The following is a short description of the infrastructure contents:

| Folder | Description |
| ------ | ----------- |
| `beautifulsoup4-4.3.2/`  | Python library used during HTML indexing |
| `docbook-xsl-ns-1.78.1/` | DocBook XSLT stylesheets that transform guides |
| `fop-1.1/`               | Apache FOP, which transforms FOP XML into a PDF |
| `perforce/`              | Contains [Perforce](perforce/README.md) XSLT customizations, ANT tasks, configuration, and assets (images, javascript, etc.) |
| `saxon-6.5.5/`           | XSLT processor |
| `sample_guide/`          | Contains a [sample guide](sample_guide/README.md) and build configuration.
| `xalan-j_2_7_1/`         | XSLT processor |

Within the [`perforce`](perforce/README.md) and [`sample_guide`](sample_guide/README.md)
folders is a `README.md` file describing the respective folder's contents in
greater detail.

<!--- vim: set ts=2 sw=2 tw=74 ai si: -->