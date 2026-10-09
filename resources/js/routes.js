/**
 * Table des URLs statiques. Les URLs d'une séance (modifier, lancer, journal)
 * sont fabriquées côté serveur et voyagent dans les payloads Inertia.
 */
export const routes = {
    home: '/',
    library: '/exercices',
    progress: '/progres',
    progressExercise: (slug) => `/progres/exercices/${slug}`,
    bodyWeights: '/progres/poids',
    targetWeight: '/progres/objectif-poids',
    newWorkout: '/seances/nouvelle',
    assistant: '/seances/assistant',
    assistantSuggest: '/seances/assistant/proposition',
    assistantComplete: '/seances/assistant/completer',
    exerciseEquivalents: (slug) => `/exercices/${slug}/equivalents`,
    zoneExercises: (zone) => `/exercices/zones/${zone}`,
    workouts: '/seances',
    settings: '/reglages',
    workout: (id) => `/seances/${id}`,
    preferences: '/reglages',
    login: '/connexion',
    register: '/inscription',
    logout: '/deconnexion',
    forgotPassword: '/mot-de-passe-oublie',
    resetPassword: '/nouveau-mot-de-passe',
};
