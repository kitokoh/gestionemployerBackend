import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'app/router/app_router.dart';

void main() {
  runApp(
    const ProviderScope(
      child: LeopardoApp(),
    ),
  );
}

class LeopardoApp extends ConsumerWidget {
  const LeopardoApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final router = ref.watch(routerProvider);

    return MaterialApp.router(
      title: 'Leopardo RH',
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: Colors.orange),
        useMaterial3: true,
        fontFamily: 'Inter',
      ),
      routerConfig: router,
      debugShowCheckedModeBanner: false,
    );
  }
}
