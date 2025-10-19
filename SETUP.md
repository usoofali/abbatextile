# Application Setup Configuration

This document describes the Application Setup Configuration system for the AbbaTextiles POS application. This system allows for easy configuration and deployment of the application in production environments, particularly useful for HPanel hosting environments after Git deployment.

## Overview

The setup system provides a web-based interface to configure the application after deployment. It handles:

-   Database configuration
-   Administrator account creation
-   Company settings setup
-   Sample data creation (optional)
-   Application optimization

## Features

### 🔧 Configuration Management

-   Application name and URL configuration
-   Database connection settings
-   Environment file updates

### 👤 User Management

-   Administrator account creation
-   Role-based access control setup
-   Secure password handling

### 🏢 Company Setup

-   Company information configuration
-   Contact details setup
-   Logo and branding options

### 📊 Sample Data

-   Pre-configured shops, categories, and products
-   Realistic test data for immediate use
-   Optional installation (can be skipped)

### 🚀 Production Optimization

-   Storage link creation for file uploads
-   Config caching
-   Route caching
-   View caching
-   Application key generation

## Usage

### Accessing Setup

1. **After Git Deployment**: Navigate to your domain (e.g., `https://yourdomain.com`)
2. **Automatic Redirect**: If no users exist, you'll be automatically redirected to `/setup`
3. **Manual Access**: You can also directly visit `/setup`

### Setup Process

#### Step 1: Application Settings

-   **Application Name**: The name of your POS system
-   **Application URL**: Your domain URL (e.g., `https://yourdomain.com`)

#### Step 2: Database Configuration

-   **Database Host**: Usually `localhost` for shared hosting
-   **Database Port**: Usually `3306` for MySQL
-   **Database Name**: Your database name
-   **Database Username**: Your database username
-   **Database Password**: Your database password

#### Step 3: Administrator Account

-   **Admin Name**: Full name of the administrator
-   **Admin Email**: Email address for login
-   **Admin Password**: Secure password (minimum 8 characters)

#### Step 4: Company Information

-   **Company Name**: Your business name
-   **Company Email**: Business email address
-   **Company Phone**: Contact phone number
-   **Company Address**: Business address

#### Step 5: Sample Data (Optional)

-   Pre-configured shops, categories, and products
-   Can be skipped if you prefer to start with a clean system

### Security Features

#### Access Control

-   Setup is only accessible when no users exist
-   Automatically redirects to login after completion
-   Middleware protection against unauthorized access

#### Data Validation

-   Comprehensive form validation
-   Email format validation
-   Password strength requirements
-   Database connection testing

## Technical Implementation

### Components

#### Setup Configuration Component

-   **Location**: `resources/views/livewire/setup/configuration.blade.php`
-   **Purpose**: Main setup interface and logic
-   **Features**: Progress tracking, error handling, logging

#### Middleware

-   **Location**: `app/Http/Middleware/EnsureSetupNotCompleted.php`
-   **Purpose**: Protects setup from unauthorized access
-   **Logic**: Redirects to login if users exist

#### Routes

-   **Setup Route**: `/setup` - Main setup interface
-   **Home Route**: `/` - Redirects based on setup status
-   **Protection**: Middleware ensures proper access control

### Database Operations

#### Migrations

-   Runs all pending migrations
-   Creates necessary database tables
-   Handles schema updates

#### Sample Data Creation

-   **Shops**: Creates a main branch location
-   **Categories**: Sets up fabric categories (Cotton, Silk, Linen, Denim)
-   **Products**: Creates sample textile products with realistic pricing

### Environment Management

#### Configuration Updates

-   Updates `.env` file with provided settings
-   Handles database connection parameters
-   Sets application name and URL

#### Optimization

-   Creates storage symbolic link for file uploads
-   Clears and caches configuration
-   Optimizes routes and views
-   Generates application key if missing

## Testing

### Test Coverage

-   **Setup Component Tests**: `tests/Feature/SetupTest.php`
-   **Middleware Tests**: `tests/Feature/SetupMiddlewareTest.php`

### Test Scenarios

-   Setup accessibility with/without existing users
-   Form validation and error handling
-   Database operations and data creation
-   Security and access control
-   Progress tracking and logging

## Deployment Workflow

### HPanel Deployment Process

1. **Git Push**: Deploy code to hosting environment
2. **Database Setup**: Create MySQL database in HPanel
3. **Access Setup**: Visit your domain to access setup interface
4. **Configuration**: Fill out the setup form with your settings
5. **Completion**: System redirects to login after successful setup

### Environment Requirements

#### Server Requirements

-   PHP 8.2 or higher
-   MySQL 5.7 or higher
-   Composer
-   Git

#### Laravel Requirements

-   All Laravel 11 requirements
-   Livewire 3
-   Volt components

## Troubleshooting

### Common Issues

#### Database Connection Failed

-   Verify database credentials in HPanel
-   Check database host and port settings
-   Ensure database user has proper permissions

#### Setup Not Accessible

-   Clear browser cache
-   Check if users already exist in database
-   Verify middleware configuration

#### Migration Errors

-   Check database permissions
-   Verify Laravel configuration
-   Review error logs for specific issues

### Error Handling

#### Progress Logging

-   Real-time progress updates
-   Detailed error messages
-   Step-by-step completion tracking

#### Recovery Options

-   Retry failed operations
-   Skip optional components
-   Manual database intervention if needed

## Security Considerations

### Access Protection

-   Setup only available during initial configuration
-   Automatic redirect after completion
-   Middleware-based access control

### Data Security

-   Password hashing using Laravel's built-in methods
-   Secure database connection handling
-   Environment file protection

### Production Security

-   Storage link creation for secure file access
-   Application key generation
-   Config optimization
-   Cache implementation for performance

## Maintenance

### Post-Setup

-   Remove setup files (optional)
-   Regular backup procedures
-   Monitor application logs
-   Update system regularly

### Re-setup (if needed)

-   Delete all users from database
-   Access `/setup` again
-   Reconfigure as needed

## Support

For issues or questions regarding the setup system:

1. Check the application logs
2. Review this documentation
3. Test with sample data first
4. Verify database connectivity
5. Check Laravel and Livewire requirements

## Future Enhancements

### Planned Features

-   Multi-language support
-   Advanced company branding options
-   Integration with external services
-   Automated backup configuration
-   Performance monitoring setup

### Customization Options

-   Custom sample data templates
-   Industry-specific configurations
-   Advanced security settings
-   Integration with payment gateways
