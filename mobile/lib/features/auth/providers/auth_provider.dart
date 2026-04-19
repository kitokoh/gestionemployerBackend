import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_rh/features/auth/data/auth_repository.dart';
import 'package:leopardo_rh/models/employee.dart';
import 'package:leopardo_rh/core/providers/core_providers.dart';
import 'package:leopardo_rh/core/api/api_exceptions.dart';

class AuthState {
  final bool isLoading;
  final Employee? employee;
  final String? error;
  final bool sessionExpired;

  AuthState({
    this.isLoading = false,
    this.employee,
    this.error,
    this.sessionExpired = false,
  });

  AuthState copyWith({
    bool? isLoading,
    Employee? employee,
    String? error,
    bool clearError = false,
    bool? sessionExpired,
  }) {
    return AuthState(
      isLoading: isLoading ?? this.isLoading,
      employee: employee ?? this.employee,
      error: clearError ? null : (error ?? this.error),
      sessionExpired: sessionExpired ?? this.sessionExpired,
    );
  }
}

class AuthNotifier extends StateNotifier<AuthState> {
  final AuthRepository _repository;

  AuthNotifier(this._repository) : super(AuthState()) {
    checkAuth();
  }

  void markSessionExpired() {
    state = AuthState(sessionExpired: true);
  }

  Future<void> checkAuth() async {
    state = state.copyWith(isLoading: true);
    final data = await _repository.checkAuth();
    if (data != null) {
      state = state.copyWith(
        isLoading: false,
        employee: data['employee'],
        sessionExpired: false,
      );
    } else {
      state = AuthState(isLoading: false);
    }
  }

  Future<bool> login(String email, String password) async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final data = await _repository.login(email, password);
      state = state.copyWith(
        isLoading: false,
        employee: data['employee'],
        sessionExpired: false,
      );
      return true;
    } catch (e) {
      if (e is ApiException) {
        state = state.copyWith(isLoading: false, error: e.message);
        return false;
      }
      state = state.copyWith(isLoading: false, error: e.toString());
      return false;
    }
  }

  Future<void> logout() async {
    await _repository.logout();
    state = AuthState(); // reset completely
  }

  Future<bool> updateProfile({
    required String firstName,
    required String lastName,
    required String email,
  }) async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final employee = await _repository.updateProfile(
        firstName: firstName,
        lastName: lastName,
        email: email,
      );
      state = state.copyWith(isLoading: false, employee: employee);
      return true;
    } catch (e) {
      if (e is ApiException) {
        state = state.copyWith(isLoading: false, error: e.message);
        return false;
      }
      state = state.copyWith(isLoading: false, error: e.toString());
      return false;
    }
  }

  Future<bool> changePassword({
    required String currentPassword,
    required String newPassword,
    required String confirmation,
  }) async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      await _repository.changePassword(
        currentPassword: currentPassword,
        newPassword: newPassword,
        confirmation: confirmation,
      );
      state = state.copyWith(isLoading: false);
      return true;
    } catch (e) {
      if (e is ApiException) {
        state = state.copyWith(isLoading: false, error: e.message);
        return false;
      }
      state = state.copyWith(isLoading: false, error: e.toString());
      return false;
    }
  }
}

final authProvider = StateNotifierProvider<AuthNotifier, AuthState>((ref) {
  return AuthNotifier(ref.watch(authRepositoryProvider));
});
