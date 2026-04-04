import 'package:dio/dio.dart';
import 'package:leopardo_rh/core/storage/secure_storage.dart';
import 'package:leopardo_rh/core/api/api_exceptions.dart';

class ApiClient {
  final Dio _dio;
  final SecureStorage _storage;

  ApiClient(this._storage)
      : _dio = Dio(BaseOptions(
          baseUrl: 'http://127.0.0.1:8000/api/v1',
          connectTimeout: const Duration(seconds: 10),
          receiveTimeout: const Duration(seconds: 10),
          headers: {'Accept': 'application/json'},
        )) {
    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          final token = await _storage.getToken();
          if (token != null) {
            options.headers['Authorization'] = 'Bearer $token';
          }
          return handler.next(options);
        },
        onError: (DioException e, handler) {
          if (e.response?.statusCode == 401) {
            // Handle auto-logout or token refresh here if needed
          }
          return handler.next(_handleError(e));
        },
      ),
    );
  }

  Dio get dio => _dio;

  DioException _handleError(DioException e) {
    String message = "Impossible de se connecter au serveur";
    String? code;
    
    if (e.response != null && e.response?.data != null) {
      if (e.response?.data is Map) {
        message = e.response?.data['message'] ?? message;
        code = e.response?.data['error'];
      }
    } else if (e.type == DioExceptionType.connectionTimeout) {
      message = "Délai de connexion dépassé";
    }

    throw ApiException(message, statusCode: e.response?.statusCode, code: code);
  }
}
