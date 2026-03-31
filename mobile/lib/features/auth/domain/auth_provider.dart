import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../data/auth_service.dart';
import '../../../shared/models/employee.dart';

class AuthState {
  final Employee? user;
  final String? token;
  final bool isAuthenticated;

  AuthState({this.user, this.token, this.isAuthenticated = false});

  AuthState copyWith({Employee? user, String? token, bool? isAuthenticated}) {
    return AuthState(
      user: user ?? this.user,
      token: token ?? this.token,
      isAuthenticated: isAuthenticated ?? this.isAuthenticated,
    );
  }
}

class AuthNotifier extends StateNotifier<AuthState> {
  final AuthService _authService;

  AuthNotifier(this._authService) : super(AuthState());

  Future<bool> login(String email, String password) async {
    try {
      final data = await _authService.login(email, password);
      final token = data['token'];
      final user = Employee.fromJson(data['user']);

      state = AuthState(user: user, token: token, isAuthenticated: true);
      return true;
    } catch (e) {
      state = AuthState(isAuthenticated: false);
      return false;
    }
  }

  void logout() {
    state = AuthState();
  }
}

final authServiceProvider = Provider((ref) => AuthService());

final authProvider = StateNotifierProvider<AuthNotifier, AuthState>((ref) {
  final authService = ref.watch(authServiceProvider);
  return AuthNotifier(authService);
});
