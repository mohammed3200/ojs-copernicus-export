# OJS Copernicus Export Plugin

A plugin for Open Journal Systems (OJS) 3.5.1 that enables export of journal issue metadata in XML format compatible with Index Copernicus International (ICI) indexing requirements.

## Features

- **XML Export**: Generate ICI-compliant XML files for journal issues
- **Validation**: Validate generated XML against ICI schema before export
- **Bulk Export**: Export complete issues with all articles, authors, and metadata
- **User-Friendly Interface**: Simple web interface for journal managers
- **Standards Compliance**: Fully compliant with ICI Journal Master List requirements

## Compatibility

- **OJS Version**: 3.5.1+
- **PHP Version**: 7.4+
- **Database**: MySQL/PostgreSQL

## Installation

1. Download the latest release from the [Releases page](https://github.com/your-username/ojs-copernicus-export/releases)
2. Extract the files to your OJS `plugins/importexport/` directory
3. Rename the directory to `copernicus`
4. Navigate to OJS Admin → Settings → Website → Plugins
5. Find "Copernicus Export Plugin" and enable it
6. Grant appropriate permissions to journal managers

## Usage

### Exporting Issues for Index Copernicus

1. Go to **Dashboard** → **Management** → **Import/Export** → **Copernicus Export**
2. Select the issue you want to export from the list
3. Choose between:
   - **Validate**: Check XML compliance before export
   - **Export**: Directly download the XML file

### Validation Features

- Schema validation against ICI standards
- Real-time XML syntax checking
- Copy XML to clipboard functionality
- Visual error reporting with line numbers

### Supported Metadata

- Journal information (ISSN, title)
- Issue metadata (volume, number, publication date)
- Article data (titles, abstracts, DOIs, pages)
- Author information (names, emails, affiliations, ORCID)
- Keywords and references
- PDF file URLs

## XML Format

The plugin generates XML files compliant with the ICI `ic-import.xsd` schema:

```xml
<ici-import xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
            xsi:noNamespaceSchemaLocation="https://journals.indexcopernicus.com/ic-import.xsd">
    <journal issn="0000-0000"/>
    <issue number="1" volume="1" year="2024" publicationDate="2024-01-01T00:00:00Z">
        <article>
            <type>ORIGINAL_ARTICLE</type>
            <languageVersion language="en">
                <title>Article Title</title>
                <abstract>Article abstract...</abstract>
                <pdfFileUrl>https://example.com/article.pdf</pdfFileUrl>
                <!-- ... more fields -->
            </languageVersion>
        </article>
    </issue>
</ici-import>