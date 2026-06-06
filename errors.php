<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error - Student Payment Management System</title>
    <link rel="icon" href="img/logo.png" type="image/png">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <!-- Main Content -->
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body text-center">
                        <?php
                        $error_code = isset($_GET['error']) ? intval($_GET['error']) : 404;
                        $error_messages = [
                            400 => ['title' => 'Bad Request', 'message' => 'The server could not understand the request due to invalid syntax.'],
                            401 => ['title' => 'Unauthorized', 'message' => 'You are not authorized to access this resource.'],
                            403 => ['title' => 'Forbidden', 'message' => 'Access to this resource is forbidden.'],
                            404 => ['title' => 'Page Not Found', 'message' => 'The page you are looking for does not exist.'],
                            500 => ['title' => 'Internal Server Error', 'message' => 'An internal server error occurred. Please try again later.'],
                            503 => ['title' => 'Service Unavailable', 'message' => 'The service is temporarily unavailable. Please try again later.']
                        ];

                        $error = $error_messages[$error_code] ?? $error_messages[404];
                        ?>
                        <h1 class="display-1 text-danger"><?php echo $error_code; ?></h1>
                        <h2><?php echo $error['title']; ?></h2>
                        <p class="lead"><?php echo $error['message']; ?></p>
                        <a href="index.php" class="btn btn-primary">Go to Homepage</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-light mt-5 py-4">
        <div class="container text-center">
            <p class="text-muted mb-0">&copy; 2025 Student Payment Management System.</p>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>