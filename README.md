# 5q12's Indexer

A PHP-based directory indexer that provides a web interface for browsing and managing files on a server. The indexer displays directory contents with customizable sorting, filtering, and download capabilities.

## Quick Start

1. Download `index.php` and place it in your desired web directory
2. Configure your web server to serve PHP files  
3. Access the indexer through your web browser

The indexer will automatically create necessary directories and download configuration files on first run.

## Features

- **No Javascript**: The custom indexer makes no use of javascript at all
- **File Listing**: Displays files and directories with size, modification date, and type information
- **Sorting**: Sort by name, size, date modified, or file type in ascending/descending order
- **File Type Configuration**: Configurable indexing and viewing rules for different file extensions
- **Download Support**: Direct file downloads and ZIP archive creation for directories
- **Icon System**: File type icons with API or local icon support
- **Caching**: SQLite or JSON-based caching system for improved performance
- **Security**: Path traversal protection and configurable access controls
- **Responsive Design**: Mobile-friendly interface with clean styling

## Documentation

### Getting Started
- **[Installation Guide](docs/installation.md)** - Complete setup procedures and server requirements
- **[User Guide](docs/user-guide.md)** - How to use the interface and features

### Configuration & Administration  
- **[Configuration Guide](docs/configuration.md)** - Detailed settings and customization options
- **[Security Guide](docs/security.md)** - Security features and hardening procedures

### Technical Reference
- **[API Reference](docs/api-reference.md)** - API endpoints and integration documentation
- **[Troubleshooting Guide](docs/troubleshooting.md)** - Common issues and solutions

## Configuration

The indexer uses a JSON configuration system with the following main sections:

### Main Settings
- `cache_type`: Choose between 'sqlite' or 'json' caching
- `local_icons`: Use local icon files instead of API icons
- `disable_api`: Disable external API calls for offline operation
- `disable_file_downloads`: Disable individual file downloads
- `disable_folder_downloads`: Disable ZIP folder downloads
- `index_hidden`: Include hidden files and folders in listings
- `index_all`: Override all exclusion rules and index everything
- `deny_list`: Comma-separated list of paths to exclude
- `allow_list`: Comma-separated list of paths to explicitly include

### File Type Control
- `exclusions`: Controls which file types are indexed
- `viewable_files`: Controls which file types can be viewed in browser vs downloaded

## API Integration

The indexer can optionally integrate with an external API for:
- Configuration updates
- Icon management
- Extension mappings
- Stylesheet delivery

When `disable_api` is set to `false`, the indexer will attempt to fetch resources from the configured API endpoint.

## File Structure

```
your-directory/
├── index.php                 # Main indexer file (required)
└── .indexer_files/           # Auto-created directory
    ├── config.json           # Configuration file
    ├── zip_cache/            # Temporary ZIP files
    ├── index_cache/          # Cache files
    ├── icons/                # Local icon files (optional)
    └── local_api/            # Local API resources (optional)
```

## Security Features

- Path traversal protection
- Directory exclusion rules
- Configurable file access controls
- Hidden file handling options

## Browser Support

The indexer works with modern web browsers and includes responsive design for mobile devices.

## Requirements

- PHP 7.0 or higher
- Web server with PHP support
- ZipArchive extension for folder downloads
- SQLite extension for SQLite caching (optional)

## License

This project is provided as-is without warranty. Use at your own discretion.

## File Type Support

The indexer recognizes and handles over 200 file extensions across multiple categories including:
- Programming languages (PHP, JavaScript, Python, etc.)
- Documents (PDF, DOCX, TXT, etc.)
- Images (PNG, JPG, GIF, etc.)
- Archives (ZIP, RAR, 7Z, etc.)
- Configuration files
- System files
- And many more

File type behavior can be customized through the configuration system.
