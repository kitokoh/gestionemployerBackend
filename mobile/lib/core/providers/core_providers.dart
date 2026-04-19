import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:leopardo_rh/core/api/api_client.dart';
import 'package:leopardo_rh/core/storage/app_preferences.dart';
import 'package:leopardo_rh/core/storage/secure_storage.dart';
import 'package:leopardo_rh/features/auth/data/auth_repository.dart';
import 'package:leopardo_rh/features/auth/providers/auth_provider.dart';
import 'package:leopardo_rh/features/attendance/data/attendance_repository.dart';
import 'package:leopardo_rh/features/settings/data/settings_repository.dart';

final secureStorageProvider = Provider<SecureStorage>((ref) {
  return SecureStorage();
});

final appPreferencesProvider = Provider<AppPreferences>((ref) {
  return AppPreferences();
});

final apiClientProvider = Provider<ApiClient>((ref) {
  final storage = ref.watch(secureStorageProvider);
  return ApiClient(
    storage,
    onUnauthorized: () {
      Future.microtask(() {
        ref.read(authProvider.notifier).markSessionExpired();
      });
    },
  );
});

final authRepositoryProvider = Provider<AuthRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  final storage = ref.watch(secureStorageProvider);
  return AuthRepository(apiClient, storage);
});

final attendanceRepositoryProvider = Provider<AttendanceRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  return AttendanceRepository(apiClient);
});

final settingsRepositoryProvider = Provider<SettingsRepository>((ref) {
  final apiClient = ref.watch(apiClientProvider);
  final preferences = ref.watch(appPreferencesProvider);
  return SettingsRepository(apiClient, preferences);
});

final themeModeProvider = StateNotifierProvider<ThemeModeNotifier, ThemeMode>((ref) {
  final preferences = ref.watch(appPreferencesProvider);

  return ThemeModeNotifier(preferences);
});

class ThemeModeNotifier extends StateNotifier<ThemeMode> {
  ThemeModeNotifier(this._preferences) : super(_parseThemeMode(_preferences.themeMode));

  final AppPreferences _preferences;

  Future<void> setThemeMode(ThemeMode mode) async {
    state = mode;
    await _preferences.saveThemeMode(mode.name);
  }

  static ThemeMode _parseThemeMode(String value) {
    return switch (value) {
      'light' => ThemeMode.light,
      'dark' => ThemeMode.dark,
      default => ThemeMode.system,
    };
  }
}
