import 'package:flutter_test/flutter_test.dart';
import 'package:leopardo_rh/main.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

void main() {
  testWidgets('App smoke test', (WidgetTester tester) async {
    // Build our app and trigger a frame.
    await tester.pumpWidget(const ProviderScope(child: LeopardoApp()));

    // Verify that we are on the login screen
    expect(find.text('LEOPARDO RH'), findsOneWidget);
    expect(find.text('Se connecter'), findsOneWidget);
  });
}
