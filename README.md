# Google Classroom Clone

A comprehensive learning management system built with PHP, MySQL, HTML, CSS, and JavaScript that replicates the core functionality of Google Classroom.

## Features

### 🎓 Teacher Module
- **Class Management**: Create, edit, archive, and delete classes with unique codes and QR codes
- **Materials & Assignments**: Upload materials, create assignments with due dates, grading options
- **Student Work**: View submissions, grade with feedback, handle late submissions
- **Attendance**: Mark daily attendance, generate attendance reports
- **Communication**: Post announcements, manage public comments and private messages
- **Notifications**: Real-time alerts for new content and student activities

### 👨‍🎓 Student Module
- **Class Participation**: Join classes via code or QR scan, view materials inline
- **Assignments**: View assignments, submit work, track grades and feedback
- **Attendance**: View personal attendance history
- **Communication**: Participate in discussions, send private messages
- **Notifications**: Get alerts for deadlines, grades, and announcements

### 👨‍💼 Admin Module
- **User Management**: Approve/block teachers, manage student accounts
- **Class Oversight**: Monitor all classes, remove inappropriate content
- **Reports**: Generate user activity logs, assignment statistics, attendance reports
- **System Settings**: Configure application settings and policies

### 🔐 Shared Features
- **Authentication**: Secure login/logout with session management
- **Role-based Access**: Separate interfaces for teachers, students, and admins
- **File Handling**: Organized file storage with preview capabilities
- **Deadline Management**: Automatic late submission detection
- **Search & Filter**: Advanced search across assignments and materials
- **Responsive Design**: Mobile and desktop friendly interface
- **Security**: Prepared statements, CSRF protection, file validation
- **Notifications**: Email and in-app notification system

## Technology Stack

- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **UI Framework**: Bootstrap 5.3
- **Icons**: Font Awesome 6.0
- **QR Code**: HTML5 QR Code Scanner
- **Email**: PHP mail() function

## Installation

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- Composer (optional, for dependency management)

### Setup Instructions

1. **Clone the repository**
   ```bash
   git clone https://github.com/yourusername/classroom-clone.git
   cd classroom-clone
   ```

2. **Configure your web server**
   - Point your web server document root to the project directory
   - Ensure PHP has write permissions for file uploads

3. **Database Setup**
   - Create a new MySQL database named `classroom_clone`
   - Update database credentials in `config/database.php`:
     ```php
     $host = 'localhost';
     $dbname = 'classroom_clone';
     $username = 'your_username';
     $password = 'your_password';
     ```

4. **File Permissions**
   ```bash
   # Create upload directories
   mkdir -p uploads/materials uploads/assignments uploads/submissions
   
   # Set permissions (Linux/Mac)
   chmod 755 uploads/
   chmod 755 uploads/materials/
   chmod 755 uploads/assignments/
   chmod 755 uploads/submissions/
   ```

5. **Access the application**
   - Open your browser and navigate to the project URL
   - The database tables will be created automatically on first access
   - Default admin credentials:
     - Email: `admin@classroom.com`
     - Password: `admin123`

## Directory Structure

```
classroom-clone/
├── admin/                 # Admin module files
│   ├── api/              # Admin API endpoints
│   ├── dashboard.php     # Admin dashboard
│   ├── users.php         # User management
│   └── ...
├── teacher/              # Teacher module files
│   ├── dashboard.php     # Teacher dashboard
│   ├── classes.php       # Class management
│   ├── assignments.php   # Assignment management
│   └── ...
├── student/              # Student module files
│   ├── dashboard.php     # Student dashboard
│   ├── join_class.php    # Class joining
│   ├── assignments.php   # Assignment submission
│   └── ...
├── api/                  # Shared API endpoints
│   ├── notifications.php # Notification system
│   ├── search.php        # Search functionality
│   └── ...
├── assets/               # Static assets
│   ├── css/             # Stylesheets
│   ├── js/              # JavaScript files
│   └── images/          # Images
├── config/              # Configuration files
│   └── database.php     # Database configuration
├── includes/            # Shared PHP functions
│   └── functions.php    # Utility functions
├── uploads/             # File upload directory
│   ├── materials/       # Class materials
│   ├── assignments/     # Assignment files
│   └── submissions/     # Student submissions
├── index.php            # Main entry point
├── logout.php           # Logout functionality
└── README.md           # This file
```

## Usage

### For Teachers
1. Register as a teacher (requires admin approval)
2. Create classes with unique codes
3. Upload materials and create assignments
4. Grade student submissions and provide feedback
5. Track attendance and generate reports

### For Students
1. Register as a student
2. Join classes using class codes or QR codes
3. Access materials and submit assignments
4. View grades and feedback
5. Participate in class discussions

### For Administrators
1. Approve teacher registrations
2. Monitor system activity
3. Generate reports and statistics
4. Manage user accounts and permissions

## Security Features

- **Password Hashing**: All passwords are hashed using PHP's `password_hash()`
- **SQL Injection Prevention**: All database queries use prepared statements
- **CSRF Protection**: Cross-site request forgery protection implemented
- **File Upload Security**: File type validation and size restrictions
- **Session Security**: Secure session handling with timeout
- **Input Sanitization**: All user inputs are sanitized and validated

## File Upload Support

The system supports the following file types:
- **Documents**: PDF, DOC, DOCX, PPT, PPTX, TXT
- **Images**: JPG, JPEG, PNG, GIF
- **Videos**: MP4, AVI, MOV

Maximum file size: 50MB per file

## Email Configuration

To enable email notifications, configure your PHP mail settings in `php.ini`:
```ini
[mail function]
SMTP = localhost
smtp_port = 25
sendmail_from = noreply@yourdomain.com
```

## Customization

### Styling
- Modify `assets/css/style.css` to customize the appearance
- Update CSS variables in `:root` for color scheme changes

### Configuration
- Edit `config/database.php` for database settings
- Modify `includes/functions.php` for application logic changes

### Adding Features
- Create new PHP files in appropriate module directories
- Add database tables as needed
- Update navigation menus in dashboard files

## Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Verify database credentials in `config/database.php`
   - Ensure MySQL service is running
   - Check database permissions

2. **File Upload Issues**
   - Verify upload directory permissions
   - Check PHP upload settings in `php.ini`
   - Ensure sufficient disk space

3. **Email Not Working**
   - Configure PHP mail settings
   - Check server mail configuration
   - Verify email addresses are valid

4. **QR Code Scanner Not Working**
   - Ensure HTTPS is enabled (required for camera access)
   - Check browser permissions for camera access
   - Verify HTML5 QR Code library is loaded

### Debug Mode

Enable debug mode by adding this to the top of PHP files:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Support

For support and questions:
- Create an issue on GitHub
- Check the troubleshooting section
- Review the code comments for implementation details

## Changelog

### Version 1.0.0
- Initial release
- Complete teacher, student, and admin modules
- File upload and management system
- Real-time notifications
- QR code functionality
- Responsive design

## Acknowledgments

- Bootstrap for the UI framework
- Font Awesome for icons
- HTML5 QR Code Scanner for QR functionality
- Google Classroom for inspiration

---

**Note**: This is a demonstration project. For production use, implement additional security measures, error handling, and performance optimizations.
