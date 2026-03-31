// mobile/lib/shared/models/evaluation.dart

class EvaluationCriterion {
  final String name;
  final int score;       // 1-5
  final String? comment;

  const EvaluationCriterion({
    required this.name,
    required this.score,
    this.comment,
  });

  factory EvaluationCriterion.fromJson(Map<String, dynamic> json) => EvaluationCriterion(
    name: json['name'] as String,
    score: json['score'] as int,
    comment: json['comment'] as String?,
  );

  Map<String, dynamic> toJson() => {
    'name': name,
    'score': score,
    if (comment != null) 'comment': comment,
  };
}

class Evaluation {
  final int id;
  final int employeeId;
  final String period;          // "2026-Q1"
  final double overallScore;
  final List<EvaluationCriterion> criteria;
  final String? globalComment;
  final bool selfEvalDone;
  final List<EvaluationCriterion> selfCriteria;
  final String? selfGlobalComment;
  final String status;          // "draft", "completed"
  final DateTime? createdAt;

  const Evaluation({
    required this.id,
    required this.employeeId,
    required this.period,
    required this.overallScore,
    required this.criteria,
    this.globalComment,
    this.selfEvalDone = false,
    this.selfCriteria = const [],
    this.selfGlobalComment,
    required this.status,
    this.createdAt,
  });

  factory Evaluation.fromJson(Map<String, dynamic> json) => Evaluation(
    id: json['id'] as int,
    employeeId: json['employee_id'] as int,
    period: json['period'] as String,
    overallScore: (json['overall_score'] as num).toDouble(),
    criteria: json['criteria'] != null
        ? (json['criteria'] as List)
            .map((e) => EvaluationCriterion.fromJson(e as Map<String, dynamic>))
            .toList()
        : [],
    globalComment: json['global_comment'] as String?,
    selfEvalDone: json['self_eval_done'] as bool? ?? false,
    selfCriteria: json['self_criteria'] != null
        ? (json['self_criteria'] as List)
            .map((e) => EvaluationCriterion.fromJson(e as Map<String, dynamic>))
            .toList()
        : [],
    selfGlobalComment: json['self_global_comment'] as String?,
    status: json['status'] as String,
    createdAt: json['created_at'] != null
        ? DateTime.parse(json['created_at'] as String)
        : null,
  );
}
