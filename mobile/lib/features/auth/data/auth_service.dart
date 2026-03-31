import 'dart:convert';
import 'package:flutter/services.dart';
import '../../../shared/models/employee.dart';

class AuthService {
  Future<Map<String, dynamic>> login(String email, String password) async {
    // Simuler un délai réseau
    await Future.delayed(const Duration(seconds: 1));

    if (email == 'ahmed.benali@entreprise.com' && password == 'password') {
      final String response = await rootBundle.loadString('assets/mock/mock_auth_login.json');
      final data = json.decode(response);
      return data['data'];
    }

    throw Exception('INVALID_CREDENTIALS');
  }

  Future<Employee> getMe() async {
    final String response = await rootBundle.loadString('assets/mock/mock_auth_me.json');
    final data = json.decode(response);
    return Employee.fromJson(data['data']);
  }
}
