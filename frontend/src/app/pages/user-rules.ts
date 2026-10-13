/** Same rules as the API (backend/src/Dto/UserInput.php). */
// Case-insensitive: usernames are lowercased before being sent
export const USERNAME_PATTERN = /^[a-z0-9._-]{3,30}$/i;
export const USERNAME_HINT =
  '3 à 30 caractères : lettres minuscules, chiffres, « . », « _ » ou « - ».';
export const PASSWORD_MIN_LENGTH = 8;
